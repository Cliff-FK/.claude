export const meta = {
  name: 'generator-critic-verifier',
  description: 'Boucle réutilisable Générateur→Critique-adversarial→Vérifieur-anti-régression (Evaluator-optimizer + Verifier loop), avec garde-fous coût/convergence, application en git worktree isolé.',
  whenToUse: 'Tâche à forte valeur où la rigueur multi-agents se justifie (le critique doit réfuter, le vérifieur exécute de VRAIS tests). PAS pour une tâche triviale/déterministe — la porte d\'entrée le refuse.',
  phases: [
    { title: 'Gate', detail: 'le multi-agent est-il justifié ?' },
    { title: 'Generate', detail: 'produire le livrable' },
    { title: 'Critique', detail: 'agent adversarial : réfuter, pas noter' },
    { title: 'Verify', detail: 'tests déterministes anti-régression' },
    { title: 'Synthesize', detail: 'best-so-far + rapport' },
  ],
}

// ── Entrées (args) ───────────────────────────────────────────────────────────
// args.task          (requis) description de la tâche / du livrable visé
// args.successCriteria (requis) critères explicites de succès (sinon la boucle ne converge pas)
// args.testCommand   (optionnel) commande de test/build déterministe ; sinon un agent la découvre
// args.maxIterations (optionnel, défaut 3) garde-fou de non-convergence
// args.minCritics    (optionnel, défaut 1) nb de critiques adversariaux par tour
// Normalise args : le runtime peut le transmettre comme objet OU comme chaîne JSON
// (notamment au re-lancement via scriptPath). On parse si c'est une string.
let input = args
if (typeof input === 'string') {
  try { input = JSON.parse(input) } catch { input = {} }
}
input = input ?? {}

const task = input.task
const successCriteria = input.successCriteria
const testCommand = input.testCommand ?? null
const MAX_ITER = Math.max(1, Math.min(input.maxIterations ?? 3, 6))
const N_CRITICS = Math.max(1, Math.min(input.minCritics ?? 1, 3))

if (!task || !successCriteria) {
  log('ABORT — `task` et `successCriteria` sont requis (sans critères explicites, la boucle ne peut pas converger).')
  return { aborted: true, reason: 'missing task/successCriteria' }
}

// ── Schemas structurés ───────────────────────────────────────────────────────
const GATE = {
  type: 'object',
  required: ['justified', 'reason', 'cheaperAlternative'],
  properties: {
    justified: { type: 'boolean', description: 'true seulement si la tâche est à forte valeur ET non triviale ET non purement déterministe' },
    reason: { type: 'string' },
    cheaperAlternative: { type: 'string', description: 'si non justifié : le workflow simple/déterministe à faire à la place' },
  },
}
const CRITIQUE = {
  type: 'object',
  required: ['defects', 'verdict'],
  properties: {
    defects: {
      type: 'array',
      items: {
        type: 'object',
        required: ['severity', 'description', 'evidence'],
        properties: {
          severity: { type: 'string', enum: ['blocker', 'major', 'minor'] },
          description: { type: 'string' },
          evidence: { type: 'string', description: 'preuve concrète (ligne, citation, contre-exemple) — pas une opinion' },
        },
      },
    },
    verdict: { type: 'string', enum: ['reject', 'accept-with-fixes', 'accept'] },
    justificationIfNoDefects: { type: 'string', description: 'OBLIGATOIRE si defects vide : pourquoi il n\'y a vraiment aucun défaut (anti-ratification)' },
  },
}
const VERIFY = {
  type: 'object',
  required: ['ranRealTests', 'commandUsed', 'pass', 'output'],
  properties: {
    ranRealTests: { type: 'boolean', description: 'false si aucun test déterministe n\'a pu être exécuté (alors pass DOIT être false)' },
    commandUsed: { type: 'string', description: 'la commande exacte exécutée (traçabilité)' },
    pass: { type: 'boolean' },
    output: { type: 'string', description: 'extrait réel de sortie (erreurs/échecs), pas un résumé' },
    regressions: { type: 'array', items: { type: 'string' } },
  },
}

// ── Phase Gate : le multi-agent est-il justifié ? (garde-fou coût ~15x) ───────
phase('Gate')
const gate = await agent(
  `Décide si une boucle multi-agents (générateur + critique adversarial + vérifieur) est JUSTIFIÉE pour cette tâche, ou si c'est de la sur-ingénierie.
Tâche : ${task}
Critères de succès : ${successCriteria}
Réponds justified=true SEULEMENT si : forte valeur ET non triviale ET la qualité bénéficie réellement d'une réfutation adversariale + vérification. Sinon justified=false et propose l'alternative la moins chère (un seul appel, ou un workflow déterministe simple). Coût d'une boucle multi-agents ≈ 15x un appel unique : ne le paie que si ça le vaut.`,
  { phase: 'Gate', schema: GATE, effort: 'low' }
)

if (!gate || !gate.justified) {
  log(`GATE: boucle NON justifiée — ${gate?.reason ?? 'gate échouée'}. Alternative: ${gate?.cheaperAlternative ?? 'faire la tâche en un appel simple'}`)
  return { aborted: true, gate }
}
log(`GATE: justifié — ${gate.reason}`)

// ── Boucle Generate → Critique → Verify ──────────────────────────────────────
let best = null
let lastDeliverable = null
let lastCritiqueFeedback = ''
const history = []

for (let iter = 1; iter <= MAX_ITER; iter++) {
  // 1) GÉNÉRATEUR (en worktree isolé : il écrit réellement le livrable)
  phase('Generate')
  const gen = await agent(
    `${iter === 1 ? 'Produis' : 'AMÉLIORE'} le livrable pour la tâche ci-dessous, dans CE worktree (écris les fichiers réels).
Tâche : ${task}
Critères de succès : ${successCriteria}
${iter > 1 ? `Défauts à corriger (du tour précédent) :\n${lastCritiqueFeedback}` : ''}
Retourne un résumé précis de ce que tu as produit/modifié (fichiers touchés + en quoi ça satisfait les critères).`,
    { phase: 'Generate', label: `generate#${iter}`, isolation: 'worktree' }
  )
  if (!gen) { log(`Generate#${iter} a échoué, arrêt.`); break }
  lastDeliverable = gen

  // 2) CRITIQUE ADVERSARIAL (jugement LLM — faillible — donc rubrique de réfutation + modèle distinct possible)
  //    N critiques en parallèle, chacun doit RÉFUTER, pas ratifier.
  phase('Critique')
  const critiques = (await parallel(
    Array.from({ length: N_CRITICS }, (_, k) => () =>
      agent(
        `Tu es un CRITIQUE ADVERSARIAL (#${k + 1}). Ta mission est de RÉFUTER ce livrable, pas de l'approuver.
Tâche visée : ${task}
Critères de succès : ${successCriteria}
Livrable produit (résumé du générateur) :
${gen}

Trouve au moins 1 défaut RÉEL (blocker/major/minor) avec une PREUVE concrète (ligne, citation, contre-exemple). Si après recherche sincère tu n'en trouves vraiment aucun, tu DOIS remplir justificationIfNoDefects en expliquant pourquoi — l'absence de défaut doit être prouvée, pas supposée. Ne ratifie jamais par confort.`,
        { phase: 'Critique', label: `critic#${iter}.${k + 1}`, schema: CRITIQUE, effort: 'high' }
      )
    )
  )).filter(Boolean)

  const allDefects = critiques.flatMap((c) => c.defects ?? [])
  const blockers = allDefects.filter((d) => d.severity === 'blocker')
  const majors = allDefects.filter((d) => d.severity === 'major')
  lastCritiqueFeedback = allDefects.length
    ? allDefects.map((d) => `- [${d.severity}] ${d.description} (preuve: ${d.evidence})`).join('\n')
    : '(aucun défaut signalé)'
  log(`Critique#${iter}: ${blockers.length} blocker, ${majors.length} major, ${allDefects.length - blockers.length - majors.length} minor`)

  // 3) VÉRIFIEUR ANTI-RÉGRESSION (DÉTERMINISTE — distinct du critique LLM)
  //    Hybride : commande fournie → exécutée telle quelle ; sinon agent qui découvre ET lance.
  phase('Verify')
  const verify = await agent(
    testCommand
      ? `Exécute EXACTEMENT cette commande de test/build dans le worktree et rapporte le résultat réel (jamais inventé) : \`${testCommand}\`. ranRealTests=true seulement si la commande s'est réellement exécutée. Si elle échoue à se lancer, ranRealTests=false et pass=false. Rapporte commandUsed et un extrait réel de output (les erreurs).`
      : `Découvre comment vérifier ce projet de façon DÉTERMINISTE (cherche package.json scripts, test runner, build, linter/typecheck) PUIS exécute-les RÉELLEMENT dans le worktree. Ne juge pas à l'œil : un test qui échoue = pass:false. Si aucun test déterministe n'existe, ranRealTests=false et pass=false (on ne valide pas sans signal réel). Rapporte la commande exacte (commandUsed) et un extrait réel de output.`,
    { phase: 'Verify', label: `verify#${iter}`, schema: VERIFY, effort: 'medium', isolation: 'worktree' }
  )
  const verifyPass = Boolean(verify?.ranRealTests && verify?.pass)
  log(`Verify#${iter}: ranRealTests=${verify?.ranRealTests} pass=${verify?.pass} cmd="${verify?.commandUsed ?? '?'}"`)

  // Score de convergence : moins de défauts pondérés + tests verts
  const weighted = blockers.length * 100 + majors.length * 10 + (allDefects.length - blockers.length - majors.length)
  const accepted = critiques.every((c) => c.verdict === 'accept') && allDefects.length === 0 && verifyPass
  const snapshot = { iter, deliverable: gen, defects: allDefects, verify, weighted, verifyPass, accepted }
  history.push(snapshot)

  // best-so-far : priorité aux tests verts, puis au moins de défauts pondérés
  if (
    !best ||
    (verifyPass && !best.verifyPass) ||
    (verifyPass === best.verifyPass && weighted < best.weighted)
  ) {
    best = snapshot
  }

  if (accepted) {
    log(`Convergé au tour ${iter} : aucun défaut + tests verts.`)
    break
  }
  if (iter === MAX_ITER) {
    log(`Max itérations (${MAX_ITER}) atteint — sortie best-so-far (tour ${best.iter}).`)
  } else if (history.length >= 2 && history[history.length - 1].weighted >= history[history.length - 2].weighted && verifyPass === history[history.length - 2].verifyPass) {
    log(`Non-amélioration détectée (tour ${iter}) — arrêt anticipé, best-so-far (tour ${best.iter}).`)
    break
  }
}

return {
  task,
  iterations: history.length,
  converged: Boolean(best?.accepted),
  best: best
    ? { iter: best.iter, verifyPass: best.verifyPass, defects: best.defects, verifyCommand: best.verify?.commandUsed, deliverable: best.deliverable }
    : null,
  note: 'Changements écrits dans un git worktree isolé — review puis merge manuel. Rien n\'est appliqué au répertoire de travail principal.',
}