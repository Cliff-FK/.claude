export const meta = {
  name: 'research-arbitrate',
  description: 'Recherche multi-sources VÉRIFIÉE et ARBITRÉE sur une question liée à un projet : balaye 5 voies de sources fixes (Context7, dépôts/guides officiels, sources officielles du sujet, sources communautaires, code/contexte du projet courant), croise les affirmations, les fait challenger par des agents adversariaux, puis un arbitre tranche selon une vue globale en signalant ce qui reste incertain. Sortie = rapport tranché et cité (ne modifie rien dans le repo).',
  whenToUse: 'Question de réflexion à enjeu où il faut croiser doc officielle, communauté et vérité terrain du projet, puis trancher — pas une simple lookup factuelle (le Gate refuse alors).',
  phases: [
    { title: 'Gate', detail: 'la recherche multi-sources est-elle justifiée ?' },
    { title: 'Cadrage', detail: 'sous-questions + voies de sources pertinentes' },
    { title: 'Sweep', detail: '5 voies de sources en parallèle, affirmations sourcées' },
    { title: 'Croisement', detail: 'dédup + accords/contradictions' },
    { title: 'Challenge', detail: 'agents adversariaux : réfuter les affirmations clés' },
    { title: 'Arbitrage', detail: 'vue globale tranche + incertitudes' },
  ],
}

// ── Entrées (args) ───────────────────────────────────────────────────────────
// args.question     (requis) la question de réflexion
// args.projectHint  (optionnel) où regarder dans le projet (chemins, libs, sujet)
// args.maxChallengers (optionnel, défaut 2, max 4) nb d'agents adversariaux par affirmation contestée
let input = args
if (typeof input === 'string') { try { input = JSON.parse(input) } catch { input = {} } }
input = input ?? {}

const question = input.question
const projectHint = input.projectHint ?? '(non précisé — découvre le contexte projet pertinent)'
const N_CHAL = Math.max(1, Math.min(input.maxChallengers ?? 2, 4))

if (!question) {
  log('ABORT — `question` requise.')
  return { aborted: true, reason: 'missing question' }
}

// ── Schemas ──────────────────────────────────────────────────────────────────
const GATE = {
  type: 'object',
  required: ['justified', 'reason', 'cheaperAlternative'],
  properties: {
    justified: { type: 'boolean', description: 'true seulement si la question a un enjeu réel ET bénéficie de croiser plusieurs types de sources + arbitrage. false si simple lookup factuelle ou réponse triviale.' },
    reason: { type: 'string' },
    cheaperAlternative: { type: 'string' },
  },
}
const SCOPE = {
  type: 'object',
  required: ['subQuestions'],
  properties: {
    subQuestions: { type: 'array', items: { type: 'string' } },
    // Les 5 voies sont TOUJOURS interrogées (le but est de croiser) — on ne les
    // conditionne pas. Le Cadrage fournit seulement des INDICES de recherche par voie
    // (où/quoi chercher) pour les rendre plus efficaces, sans jamais en désactiver une.
    hints: {
      type: 'object',
      properties: {
        context7: { type: 'string', description: 'lib/sujet probable à résoudre dans Context7' },
        officialRepos: { type: 'string', description: 'éditeur/orga GitHub probable' },
        officialDocs: { type: 'string', description: 'site/doc officielle probable' },
        community: { type: 'string', description: 'termes/forums probables' },
        project: { type: 'string', description: 'chemins/fichiers/version à inspecter dans le projet' },
      },
    },
    notes: { type: 'string' },
  },
}
const FINDINGS = {
  type: 'object',
  required: ['voie', 'claims'],
  properties: {
    voie: { type: 'string' },
    claims: {
      type: 'array',
      items: {
        type: 'object',
        required: ['claim', 'source', 'confidence'],
        properties: {
          claim: { type: 'string', description: 'affirmation atomique et vérifiable' },
          source: { type: 'string', description: 'URL / fichier:ligne / ID Context7 — TRAÇABLE, jamais "de mémoire"' },
          confidence: { type: 'string', enum: ['high', 'medium', 'low'] },
        },
      },
    },
    notFound: { type: 'string', description: 'ce qui a été cherché mais non trouvé (ex. "aucun repo officiel" APRÈS recherche, pas par défaut)' },
  },
}
const CROSS = {
  type: 'object',
  required: ['agreements', 'contradictions', 'keyClaimsToChallenge', 'solidClaims'],
  properties: {
    agreements: { type: 'array', items: { type: 'string' } },
    contradictions: { type: 'array', items: { type: 'object', properties: { topic: { type: 'string' }, positions: { type: 'string' } } } },
    keyClaimsToChallenge: { type: 'array', items: { type: 'string' }, description: 'les affirmations pivot dont dépend la réponse ET qui méritent réfutation : contestées entre voies, OU mono-source, OU confiance ≤ medium. Une claim pivot corroborée par 2+ voies en high ne va PAS ici — elle va dans solidClaims.' },
    solidClaims: { type: 'array', items: { type: 'string' }, description: 'affirmations pivot corroborées par 2+ voies indépendantes avec confiance high — considérées solides, dispensées de challenge (elles entrent à l\'arbitrage marquées comme telles)' },
  },
}
const CHALLENGE = {
  type: 'object',
  required: ['claim', 'survives', 'evidence'],
  properties: {
    claim: { type: 'string' },
    survives: { type: 'boolean', description: 'false si tu parviens à la réfuter ou la nuancer sérieusement' },
    evidence: { type: 'string', description: 'preuve concrète (contre-source, contre-exemple, version réelle) — pas une opinion' },
  },
}
const VERDICT = {
  type: 'object',
  required: ['answer', 'reasoning', 'confidence', 'uncertainties'],
  properties: {
    answer: { type: 'string', description: 'la réponse finale tranchée' },
    reasoning: { type: 'string', description: 'comment l\'arbitrage a pesé les sources (au cas par cas : selon la question, vérité terrain projet OU officiel OU communauté prime — JUSTIFIER le choix)' },
    confidence: { type: 'string', enum: ['high', 'medium', 'low'] },
    uncertainties: { type: 'array', items: { type: 'string' }, description: 'ce qui reste non résolu / à vérifier empiriquement' },
    keySources: { type: 'array', items: { type: 'string' } },
  },
}

// ── Phase Gate ───────────────────────────────────────────────────────────────
phase('Gate')
const gate = await agent(
  `Décide si une recherche multi-sources arbitrée (5 voies + croisement + challenge + arbitrage) est JUSTIFIÉE, ou si c'est de la sur-ingénierie pour cette question.
Question : ${question}
justified=true seulement si la question a un vrai enjeu ET gagne à croiser doc officielle / communauté / vérité terrain du projet puis trancher. Si c'est une lookup factuelle simple ou une réponse évidente → justified=false + alternative la moins chère.`,
  { phase: 'Gate', schema: GATE, effort: 'low' }
)
if (!gate || !gate.justified) {
  log(`GATE: non justifié — ${gate?.reason ?? 'échec gate'}. Alternative: ${gate?.cheaperAlternative ?? 'répondre en un appel'}`)
  return { aborted: true, gate }
}
log(`GATE: justifié — ${gate.reason}`)

// ── Phase Cadrage ────────────────────────────────────────────────────────────
phase('Cadrage')
const scope = await agent(
  `Cadre cette question de recherche.
Question : ${question}
Contexte projet : ${projectHint}
1) Décompose en sous-questions atomiques. 2) Pour CHACUNE des 5 voies (context7, officialRepos, officialDocs, community, project), donne un INDICE de recherche (où/quoi chercher) — on les interrogera TOUTES, le but est de croiser ; tu ne désactives aucune voie. Pour 'project', identifie précisément les chemins/fichiers/version réelle à inspecter.`,
  { phase: 'Cadrage', schema: SCOPE, effort: 'medium' }
)
// Les 5 voies sont TOUJOURS lancées (inconditionnel) : une voie sans résultat le RAPPORTE
// via notFound, elle n'est jamais coupée d'avance. C'est ce qui garantit le croisement.
const voies = ['context7', 'officialRepos', 'officialDocs', 'community', 'project']
const hints = scope?.hints ?? {}
log(`Cadrage: ${scope?.subQuestions?.length ?? 0} sous-questions, 5 voies interrogées`)

// ── Phase Sweep : 5 voies fixes, en parallèle (barrière : on croise APRÈS tout) ─
phase('Sweep')
// Doctrine ressources :
//  - SKILLS : les agents peuvent invoquer un skill spécialisé (outil Skill) si pertinent —
//    laissé à leur jugement, pas d'injonction ici.
//  - MCP : utilisé UNIQUEMENT là où le workflow le prescrit explicitement (voie context7
//    ci-dessous). Pas d'usage MCP implicite dans les autres voies.
//  - AGENTS : ce script orchestre librement autant d'agents que nécessaire, bornés par les
//    caps (N_CHAL≤4, ≤8 affirmations challengées) + caps runtime (16 concurrents).
// Économie d'input partagée par les voies web : le Sweep est le poste input dominant
// du run — on borne les fetches sans borner la recherche (chercher large, charger ciblé).
const FETCH_ECONOMY = `RÈGLE D'ÉCONOMIE : cherche large mais ne CHARGE que 2-3 pages ciblées maximum ; extrais les claims au fil de l'eau, ne charge jamais une page entière "au cas où".`
const VOIE_PROMPT = {
  context7: `Voie CONTEXT7 (quota partagé — économe) : interroge la doc à jour via Context7. RÈGLES D'ÉCONOMIE : (a) si tu connais déjà l'ID de lib (ex. /withastro/docs, /wordpress/gutenberg, /freemius/freemius-js), appelle query-docs DIRECTEMENT — saute resolve-library-id ; ne l'utilise que pour une lib inconnue ; (b) regroupe TOUS tes besoins doc en UN SEUL query-docs à 'topic' large, jamais une rafale de requêtes étroites. Extrais des affirmations sourcées (ID Context7). Si le sujet n'a pas de lib Context7, dis-le dans notFound.`,
  officialRepos: `Voie DÉPÔTS/GUIDES OFFICIELS : cherche les repos GitHub officiels / guides de référence de l'éditeur du sujet (et un éventuel skill-creator, llms.txt, .well-known). Affirmations sourcées (URLs réelles). Ne conclus "n'existe pas" qu'APRÈS recherche. ${FETCH_ECONOMY}`,
  officialDocs: `Voie DOC OFFICIELLE DU SUJET : la documentation officielle (site éditeur, spec). Affirmations sourcées (URLs). Chiffres/limites/signatures exacts si pertinents. ${FETCH_ECONOMY}`,
  community: `Voie COMMUNAUTÉ : sources communautaires sérieuses (Stack Overflow, blogs techniques reconnus, issues GitHub, discussions). Affirmations sourcées + signale leur fiabilité (officiel-adjacent vs opinion isolée). ${FETCH_ECONOMY}`,
  project: `Voie PROJET (vérité terrain) : inspecte le CODE et le contexte du projet courant (Grep/Glob/Read, package.json, versions réellement installées dans node_modules, conventions). Affirmations sourcées en fichier:ligne. C'est la réalité du terrain, prioritaire sur les généralités quand la question porte sur "ici".
Indices projet : ${projectHint}`,
}
// Routage par RÔLE générique : la voie 'project' (fouille du code/terrain) va à
// l'agent Explore, taillé pour balayer une codebase et localiser sans tout lire.
// Les autres voies restent en agent générique (recherche web/MCP ouverte).
// Fallback sûr : si 'Explore' n'existe pas, le runtime retombe sur le générique.
const AGENT_TYPE = { project: 'Explore' }
// Routage MODÈLE par nature de tâche : les 4 voies de COLLECTE web tournent sur Sonnet
// (récupération/extraction bien spécifiée = sa zone ; ~40-60 % moins cher par token),
// en GARDANT effort high — on ne cumule jamais les deux downgrades (modèle ET effort).
// La voie 'project' (vérité terrain, prioritaire à l'arbitrage) et toutes les phases
// d'analyse (Croisement/Challenge/Arbitrage) restent sur le modèle de session.
// Chaque sous-agent a un contexte isolé → aucun coût de cache lié au changement de modèle.
const VOIE_MODEL = { context7: 'sonnet', officialRepos: 'sonnet', officialDocs: 'sonnet', community: 'sonnet' }
const findings = (await parallel(
  voies.map((v) => () =>
    agent(
      `${VOIE_PROMPT[v]}\n\nQuestion globale : ${question}\nSous-questions : ${(scope?.subQuestions ?? []).join(' | ')}${hints[v] ? `\nIndice de recherche pour cette voie : ${hints[v]}` : ''}\nRends des claims atomiques, chacune avec sa source TRAÇABLE et un niveau de confiance. Si cette voie ne donne rien, remplis notFound (ne jamais conclure "rien" sans avoir cherché). Jamais d'affirmation "de mémoire" sans source.`,
      { phase: 'Sweep', label: `sweep:${v}`, schema: FINDINGS, effort: 'high', ...(AGENT_TYPE[v] ? { agentType: AGENT_TYPE[v] } : {}), ...(VOIE_MODEL[v] ? { model: VOIE_MODEL[v] } : {}) }
    )
  )
)).filter(Boolean)
const allClaims = findings.flatMap((f) => (f.claims ?? []).map((c) => ({ ...c, voie: f.voie })))
log(`Sweep: ${allClaims.length} affirmations sur ${findings.length} voies`)

// ── Phase Croisement (barrière nécessaire : besoin de TOUTES les voies) ───────
phase('Croisement')
const cross = await agent(
  `Croise ces affirmations issues de voies différentes. Déduplique, liste les ACCORDS (plusieurs voies concordent), les CONTRADICTIONS (voies en désaccord — précise les positions), puis TRIE les affirmations pivot dont dépend la réponse finale :
- keyClaimsToChallenge : celles qui méritent réfutation (contestées entre voies, OU mono-source, OU confiance ≤ medium) ;
- solidClaims : celles corroborées par 2+ voies indépendantes en high — solides, on ne dépense pas de challenge dessus.
Affirmations (voie | claim | source | confiance) :
${allClaims.map((c) => `- [${c.voie}/${c.confidence}] ${c.claim}  (src: ${c.source})`).join('\n')}`,
  { phase: 'Croisement', schema: CROSS, effort: 'high' }
)
const toChallenge = (cross?.keyClaimsToChallenge?.length ? cross.keyClaimsToChallenge : allClaims.filter((c) => c.confidence !== 'high').map((c) => c.claim)).slice(0, 8)
log(`Croisement: ${cross?.agreements?.length ?? 0} accords, ${cross?.contradictions?.length ?? 0} contradictions, ${toChallenge.length} à challenger, ${cross?.solidClaims?.length ?? 0} solides (dispensées)`)

// ── Phase Challenge : N challengers à LENTILLES DIFFÉRENCIÉES par claim pivot ──
// Pas N copies du même prompt : la diversité de perspectives attrape les modes
// d'échec que la redondance rate (même coût, couverture supérieure). N_CHAL
// sélectionne les N premières lentilles.
phase('Challenge')
const LENSES = [
  `lentille CONTRE-SOURCE OFFICIELLE : cherche la doc, spec ou changelog officiel qui contredit ou nuance l'affirmation (API réelle, limites chiffrées, version exacte)`,
  `lentille VÉRITÉ TERRAIN PROJET : confronte l'affirmation au code et aux versions réellement installées du projet courant (Grep/Read, package.json, node_modules)`,
  `lentille CONTRE-EXEMPLE COMMUNAUTAIRE : cherche issues GitHub, bug reports ou retours sérieux montrant qu'elle ne tient pas en pratique`,
  `lentille OBSOLESCENCE : vérifie si l'affirmation était vraie mais ne l'est plus (breaking change, dépréciation, changement de version)`,
]
const challenged = (await parallel(
  toChallenge.flatMap((claim) =>
    Array.from({ length: N_CHAL }, (_, k) => () =>
      agent(
        `Tu es un challenger ADVERSARIAL — ${LENSES[k % LENSES.length]}. Tente de RÉFUTER ou nuancer sérieusement cette affirmation par TA lentille. survives=false si tu y parviens. Ne ratifie pas par confort ; si elle tient vraiment sous ta lentille, dis pourquoi avec preuve.
Affirmation : "${claim}"
Question globale : ${question}`,
        { phase: 'Challenge', label: `challenge:lens${(k % LENSES.length) + 1}`, schema: CHALLENGE, effort: 'high' }
      )
    )
  )
)).filter(Boolean)
// Consolidation : une affirmation est "fragile" si une majorité de challengers la réfute.
const byClaim = {}
for (const c of challenged) {
  ;(byClaim[c.claim] ??= []).push(c)
}
const claimVerdicts = Object.entries(byClaim).map(([claim, votes]) => {
  const refuted = votes.filter((v) => !v.survives).length
  return { claim, fragile: refuted * 2 >= votes.length, refuted, total: votes.length, evidence: votes.map((v) => v.evidence) }
})
log(`Challenge: ${claimVerdicts.filter((v) => v.fragile).length}/${claimVerdicts.length} affirmations pivot fragilisées`)

// ── Phase Arbitrage : vue globale, tranche au cas par cas ─────────────────────
phase('Arbitrage')
const verdict = await agent(
  `Tu es l'ARBITRE. Tranche la réponse finale selon une vue GLOBALE.
Question : ${question}

ACCORDS entre voies : ${(cross?.agreements ?? []).join(' | ') || '—'}
CONTRADICTIONS : ${(cross?.contradictions ?? []).map((c) => `${c.topic}: ${c.positions}`).join(' | ') || '—'}
CLAIMS SOLIDES (corroborées par 2+ voies en high — dispensées de challenge, à traiter comme fiables) : ${(cross?.solidClaims ?? []).join(' | ') || '—'}
VERDICTS DE CHALLENGE (affirmation → fragile ?) :
${claimVerdicts.map((v) => `- "${v.claim}" → ${v.fragile ? 'FRAGILE' : 'tient'} (${v.refuted}/${v.total} réfutent)`).join('\n')}
Toutes les affirmations sourcées :
${allClaims.map((c) => `- [${c.voie}] ${c.claim} (src: ${c.source})`).join('\n')}

Règles d'arbitrage : pèse les sources AU CAS PAR CAS selon la nature de la question — si elle porte sur "comment c'est fait dans CE projet" ou sur une version réellement installée, la vérité terrain PROJET prime ; si c'est une question factuelle d'API/spec, l'OFFICIEL prime ; la communauté éclaire mais ne tranche pas seule. JUSTIFIE le poids donné. Écarte les affirmations FRAGILES sauf si une source forte les rétablit. Donne la réponse tranchée, ta confiance, et liste honnêtement ce qui reste INCERTAIN (à vérifier empiriquement).`,
  { phase: 'Arbitrage', schema: VERDICT, effort: 'high' }
)

return {
  question,
  voiesUtilisees: voies,
  nbClaims: allClaims.length,
  contradictions: cross?.contradictions ?? [],
  solidClaims: cross?.solidClaims ?? [],
  fragileClaims: claimVerdicts.filter((v) => v.fragile).map((v) => v.claim),
  verdict,
  note: 'Recherche en lecture seule — aucun fichier modifié. Sources tracées dans verdict.keySources et dans les claims.',
}
