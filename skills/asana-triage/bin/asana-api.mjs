#!/usr/bin/env node
// Module API Asana réutilisable — global, scalable, sans gid/nom/chemin en dur.
// Identité + workspaces découverts via /users/me ; pagination automatique ; Premium détecté
// dynamiquement (402 sur /search). Le token est lu depuis le Windows Credential Manager via
// asana-cred.ps1 (jamais passé en argument de ligne de commande).
//
// Usage : node asana-api.mjs <cmd>
//   me                         -> identité + workspaces (JSON)
//   assigned [--involved]      -> tickets non terminés assignés (et follower si --involved), TOUS workspaces, paginés
//   task <gid>                 -> détail d'une tâche
//   stories <gid>              -> commentaires + stories
//   attachments <gid>          -> pièces jointes (download_url inclus)
//   comment <gid> <texte...>   -> poste un commentaire (le texte n'est pas un secret)

import { execFileSync } from 'node:child_process';
import path from 'node:path';
import os from 'node:os';

const BASE = 'https://app.asana.com/api/1.0';

function readToken() {
  if (process.env.ASANA_PAT && process.env.ASANA_PAT.trim()) return process.env.ASANA_PAT.trim();
  // helper situé à côté de ce module -> aucun chemin utilisateur en dur
  const ps1 = path.join(path.dirname(new URL(import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, '$1')), 'asana-cred.ps1');
  const out = execFileSync('powershell.exe', ['-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', ps1, '-Action', 'read'], { encoding: 'utf8' });
  const tok = out.trim();
  if (!tok) throw new Error('Token Asana introuvable (Credential Manager vide). Lancer le store du PAT.');
  return tok;
}

const TOKEN = readToken();
const AUTH = { Authorization: 'Bearer ' + TOKEN };

async function api(pathq, opts = {}) {
  const r = await fetch(BASE + pathq, { ...opts, headers: { ...AUTH, 'Content-Type': 'application/json', ...(opts.headers || {}) } });
  let body = {};
  try { body = await r.json(); } catch { /* corps vide */ }
  return { status: r.status, body };
}

// GET paginé : suit next_page.offset jusqu'au bout (anti-troncature silencieuse)
async function getAll(pathq, optFields) {
  const sep = pathq.includes('?') ? '&' : '?';
  const base = pathq + sep + 'limit=100' + (optFields ? '&opt_fields=' + optFields : '');
  const all = [];
  let url = base, guard = 0;
  while (url && guard++ < 100) {
    const { status, body } = await api(url);
    if (status !== 200) return { error: body.errors || status, status, data: all };
    all.push(...(body.data || []));
    const off = body.next_page && body.next_page.offset;
    url = off ? base + '&offset=' + encodeURIComponent(off) : null;
  }
  return { data: all };
}

async function me() {
  const { status, body } = await api('/users/me?opt_fields=gid,name,workspaces.name');
  if (status !== 200 || !body.data) throw new Error('users/me a échoué : ' + JSON.stringify(body.errors || status));
  return body.data;
}

// Assignés (+ follower si involved) non terminés, sur TOUS les workspaces, dédupliqués par gid
async function listAssigned({ involved = false } = {}) {
  const u = await me();
  const notes = [];
  const map = new Map();
  for (const ws of u.workspaces) {
    const a = await getAll(`/tasks?assignee=me&workspace=${ws.gid}&completed_since=now`,
      'name,permalink_url,due_on,completed,projects.name,num_attachments,assignee.name,memberships.section.name');
    for (const t of (a.data || [])) if (!t.completed && !map.has(t.gid)) map.set(t.gid, { ...t, _role: 'assigned', _workspace: ws.name });
    if (involved) {
      const s = await getAll(`/workspaces/${ws.gid}/tasks/search?followers.any=${u.gid}&completed=false`,
        'name,permalink_url,due_on,projects.name,num_attachments,assignee.name,memberships.section.name');
      if (s.error) notes.push({ _note: `search indisponible sur "${ws.name}" (probable non-Premium)`, status: s.status });
      else for (const t of (s.data || [])) if (!map.has(t.gid)) map.set(t.gid, { ...t, _role: 'follower', _workspace: ws.name });
    }
  }
  return { user: { gid: u.gid, name: u.name }, count: map.size, tickets: [...map.values()], notes };
}

const [cmd, ...rest] = process.argv.slice(2);
const args = rest.filter(a => !a.startsWith('--'));
const flags = new Set(rest.filter(a => a.startsWith('--')).map(a => a.slice(2)));
const out = (v) => console.log(JSON.stringify(v, null, 2));

try {
  switch (cmd) {
    case 'me': out(await me()); break;
    case 'assigned': out(await listAssigned({ involved: flags.has('involved') })); break;
    case 'task': {
      const { body } = await api(`/tasks/${args[0]}?opt_fields=name,notes,html_notes,assignee.name,due_on,completed,permalink_url,projects.name,memberships.section.name,custom_fields.name,custom_fields.display_value,num_subtasks,created_at,modified_at`);
      out(body.data); break;
    }
    case 'stories': { const r = await getAll(`/tasks/${args[0]}/stories`, 'type,resource_subtype,text,created_by.name,created_at'); out(r.data || r); break; }
    case 'attachments': { const r = await getAll(`/attachments?parent=${args[0]}`, 'name,resource_subtype,download_url,permanent_url,host'); out(r.data || r); break; }
    case 'comment': {
      const { status, body } = await api(`/tasks/${args[0]}/stories`, { method: 'POST', body: JSON.stringify({ data: { text: args.slice(1).join(' ') } }) });
      out({ status, result: body.data ? { gid: body.data.gid, created_by: body.data.created_by && body.data.created_by.name } : body.errors }); break;
    }
    default:
      console.error('usage: me | assigned [--involved] | task <gid> | stories <gid> | attachments <gid> | comment <gid> <texte...>');
      process.exit(2);
  }
} catch (e) {
  console.error('ERREUR: ' + e.message);
  process.exit(1);
}
