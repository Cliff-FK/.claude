---
name: asana-triage
description: >-
  Triage industrialisé des tickets Asana de l'utilisateur. Détecte ses tickets ASSIGNÉS non terminés
  (tous les workspaces, identité et workspaces découverts dynamiquement), les comprend (texte +
  commentaires + pièces jointes images/PDF analysées visuellement), identifie par preuve le dossier
  projet local sous la racine web locale, produit un rapport + un début de correctif par ticket —
  PUIS, sur validation explicite « go » de l'utilisateur, lance les corrections en fan-out (1 agent
  par correction, max 5 en parallèle) avec validation adversariale et test Playwright réel si pas sûr
  à 100 %. À utiliser quand l'utilisateur demande de « voir / lister / traiter / triager mes tickets
  Asana », « qu'est-ce qui m'est assigné », « analyse mes tickets », ou veut démarrer les correctifs
  de ses tickets. Le pipeline lit en Asana et peut poster une relance de commentaire sur un ticket
  incompris ; il ne corrige le code QUE sur go explicite.
---

# Asana triage

Pipeline : **détecter → comprendre → router vers le projet → proposer → (sur go) corriger en fan-out**.
**Rien en dur** : identité, workspaces et Premium sont découverts au runtime via le module `asana-api.mjs`
(qui lit le token depuis le Credential Manager, pagine, et déduplique). Le token ne transite jamais en argv.

## 0. Connexion & découverte
```bash
API="$USERPROFILE\.claude\skills\asana-triage\bin\asana-api.mjs"   # chemin résolu au runtime, pas de username en dur
node "$API" me            # -> identité + workspaces (aucun gid supposé)
```
Si erreur « Token introuvable » : demander le PAT à l'utilisateur et le stocker, puis revérifier :
```bash
CRED="$USERPROFILE\.claude\skills\asana-triage\bin\asana-cred.ps1"
ASANA_CRED_IN='<pat>' powershell.exe -NoProfile -File "$CRED" -Action store
```
Le PAT Asana est full-access (pas de read-only) ; il est stocké chiffré (vault utilisateur Windows).

## 1. Détecter (assignés non terminés, tous workspaces, paginé)
```bash
node "$API" assigned              # assignés uniquement (périmètre par défaut)
node "$API" assigned --involved   # + tickets où l'utilisateur est follower (Premium détecté dynamiquement)
```
- Sortie JSON : `{ user, count, tickets[], notes[] }`. Chaque ticket a `_role` (assigned/follower) et `_workspace`.
- Pagination et dédup par gid gérées par le module (anti-troncature). `notes[]` signale les workspaces où `search` est indisponible (non-Premium) — **échec visible, pas silencieux**.
- « Mentionné » n'est pas filtrable par l'API (limite assumée).
- Périmètre par défaut = **assignés**. N'activer `--involved` que si l'utilisateur demande « de près ou de loin / impliqué ».

## 2. Comprendre — fan-out d'analyse (1 agent par ticket, max 5 en parallèle)
Pour chaque ticket, déléguer à l'agent **`asana-ticket-analyzer`** (gid + nom + permalink en entrée). Il lit le ticket+commentaires+PJ via le module, analyse les images, **trouve le dossier projet par preuve**, propose un début de fix. Traiter par **lots de 5** ; enchaîner les lots jusqu'à épuisement (scalable).

Agréger en **rapport** : un bloc par ticket — titre/statut, problématique reformulée, **dossier projet local prouvé**, fichiers suspects, début de correctif, **statut COMPRIS / PARTIEL / INCOMPRIS**.

## 3. Tickets incompris → relance autorisée
Pour un ticket **INCOMPRIS** ou **PARTIEL faute d'info** (URL/symptôme manquants), poster une relance ciblée (signée au nom de l'utilisateur, réversible). Le texte n'est pas un secret ; le token reste interne au module :
```bash
node "$API" comment <gid> "Salut, pour avancer il me manque : 1) l'URL exacte, 2) le symptôme précis, 3) le navigateur. Merci."
```
Montrer le texte posté à l'utilisateur ; story supprimable via `DELETE /stories/{gid}`.

## 4. Présenter + GATE « go »
Présenter le rapport. **Ne jamais corriger sans validation explicite.** Demander à l'utilisateur lesquels lancer (il peut n'en vouloir qu'une partie). Les tickets PARTIEL/INCOMPRIS sans piste sûre restent en attente d'info.

## 5. Corriger — fan-out de correction (sur go : 1 agent par correction, max 5)
Pour chaque correction validée, déléguer à un agent qui corrige **dans le bon dossier projet** identifié.
- **Routage préférentiel** : router vers l'agent spécialisé le plus pertinent pour le projet ciblé (ex. pour un projet builder/morph-blocks, un agent `morph-*` ou `regression-tester`) plutôt que `general-purpose`. Découvrir les agents disponibles, ne pas en supposer.
- Doctrine de l'utilisateur (cf. `~/.claude/CLAUDE.md`) : **cause racine** (pas de rustine), **DRY** sur le code/filters/hooks **du projet ciblé**, rien en dur, code moderne perf/sécu/LCP.
- Plusieurs agents sur **le même projet en parallèle** → isoler en **git worktree** ; sinon édition directe.
- Chaque agent produit le diff + un test qui prouve le **comportement** corrigé.

## 6. Valider — adversarial + Playwright
- Pour chaque correctif : **agent adversarial indépendant** chargé de **réfuter** le fix (régression, cas limite, cause non traitée). Rejeter si la réfutation tient.
- Confiance < 100 % → **test Playwright réel** sur le site local (vraies actions : clic, Ctrl+S, resize — jamais une simulation programmatique). Réutiliser `wp-save-ui-test` / `verify` si pertinent.
- Ne déclarer « résolu » qu'après validation par signal sémantique DIRECT (l'élément réel ciblé), jamais par proxy.

## Garde-fous
- Lecture Asana libre ; **écriture Asana** limitée à la relance de commentaire ; **écriture code** seulement après go.
- Token jamais en clair (lu en interne par le module, jamais en argv/historique).
- Échouer visiblement : ne jamais rapporter un succès si une étape a été sautée ou un workspace ignoré en silence.
