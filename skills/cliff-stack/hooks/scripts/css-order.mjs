#!/usr/bin/env node
/**
 * css-order — ordre canonique des declarations CSS/SCSS (« sablier inverse »).
 *
 * SPEC NORMATIVE (source de verite unique, ne pas la dupliquer ailleurs)
 * ---------------------------------------------------------------------
 * Rang de type, calcule sur la VALEUR :
 *   1  mot-cle pur ................... flex, absolute, center, space-between
 *   2  fonction / litteral arbitraire . blur(4px), rgba(...), calc(...), #fff, "..."
 *   3  variable ...................... var(--wbd-transi)
 *   4  valeur avec unite ............. 100%, 4px, .3s, 1px solid red
 *   5  chiffre nu .................... 1, 0, 1.5
 *
 * Ordre d'un bloc :
 *   MONTEE   = tous les rang 1, par longueur croissante ; puis, tant qu'elles
 *              prolongent la croissance, les declarations restantes strictement
 *              plus longues que la derniere posee (la plus petite candidate d'abord).
 *   DESCENTE = tout le reste, par longueur decroissante.
 *   Egalite de longueur : rang croissant, puis ordre d'ecriture d'origine.
 *   Aucun rang 1 dans le bloc : pas de montee, tout en descente.
 * Le losange est garanti par construction, jamais par chance.
 *
 * Longueur = texte reel de la declaration (« display: flex; »), indentation et
 * commentaire de fin de ligne exclus. Le tri est donc WYSIWYG.
 *
 * GARDE-FOU DUR : deux declarations qui se recouvrent (meme propriete, ou paire
 * shorthand/longhand) conservent leur ordre relatif d'origine. Le tri ne permute
 * que des declarations independantes (tri topologique sur l'ordre cible).
 *
 * Ce qui coupe une suite triable : bloc imbrique, at-rule, $var SCSS, custom
 * property, declaration multi-lignes, ligne vide, commentaire sur sa propre ligne.
 * Un commentaire court en fin de ligne est tolere et suit sa declaration.
 *
 * MODES
 *   --hook            payload hook Claude Code sur stdin, rapport stderr + exit 2
 *   --check <paths>   rapport, exit 1 si non conforme
 *   --write <paths>   reecriture
 *   --audit <paths>   oracle : non-perte, ordre des collisions, idempotence
 */

import { readFileSync, writeFileSync, readdirSync, statSync } from 'node:fs';
import { join, extname } from 'node:path';

const EXCLUDE = /[\\/](node_modules|vendor|dist|build|wp-admin|wp-includes|\.git)[\\/]|\.min\.(css|scss)$/i;
const MAX_REPORTED_BLOCKS = 5;

/* ------------------------------------------------------------------ parsing */

function skipString(src, i) {
  const quote = src[i];
  let j = i + 1;
  while (j < src.length) {
    if (src[j] === '\\') { j += 2; continue; }
    if (src[j] === quote) return j + 1;
    j++;
  }
  return j;
}

function parse(src) {
  const root = { kind: 'root', prelude: '', items: [], bodyStart: 0, bodyEnd: src.length };
  const blocks = [root];
  const stack = [root];
  let i = 0, itemStart = 0, paren = 0;

  const lineStartOf = (pos) => { let s = pos; while (s > 0 && src[s - 1] !== '\n') s--; return s; };
  const lineEndOf = (pos) => {
    let e = pos;
    while (e < src.length && src[e] !== '\n') e++;
    while (e > 0 && src[e - 1] === '\r') e--;
    return e;
  };
  const top = () => stack[stack.length - 1];

  const pushComment = (start, end) => {
    const node = top();
    const prev = node.items[node.items.length - 1];
    const gap = src.slice(itemStart, start);
    const trailing = /^[ \t]*$/.test(gap) && prev && !src.slice(prev.end, start).includes('\n');
    if (trailing) prev.trailingCommentEnd = end;
    else node.items.push({ kind: 'comment', start, end });
    itemStart = end;
  };

  const pushStatement = (start, end) => {
    const raw = src.slice(start, end);
    const text = raw.trim();
    if (!text) return;
    const textStart = start + raw.indexOf(text[0]);
    const node = top();
    const item = { start: textStart, end, lineStart: lineStartOf(textStart), lineEnd: lineEndOf(end - 1) };
    const m = /^([-a-zA-Z_][a-zA-Z0-9_-]*)\s*:\s*([\s\S]+?);?$/.exec(text);
    if (text[0] === '@' || text[0] === '$' || text.startsWith('--') || !m) {
      item.kind = 'other';
    } else {
      item.kind = 'decl';
      item.prop = m[1].toLowerCase();
      item.value = m[2].trim();
      item.multiline = text.includes('\n');
      item.sortLen = src.slice(item.lineStart, end).trim().length;
    }
    node.items.push(item);
  };

  while (i < src.length) {
    const c = src[i];
    if (c === '"' || c === "'") { i = skipString(src, i); continue; }
    if (c === '/' && src[i + 1] === '*') {
      const j = src.indexOf('*/', i + 2);
      const end = j < 0 ? src.length : j + 2;
      pushComment(i, end); i = end; continue;
    }
    if (c === '/' && src[i + 1] === '/') {
      let j = i;
      while (j < src.length && src[j] !== '\n') j++;
      pushComment(i, j); i = j; continue;
    }
    if (c === '(') { paren++; i++; continue; }
    if (c === ')') { if (paren > 0) paren--; i++; continue; }
    if (paren > 0) { i++; continue; }
    if (c === ';') { pushStatement(itemStart, i + 1); itemStart = i + 1; i++; continue; }
    if (c === '{') {
      const prelude = src.slice(itemStart, i).trim().replace(/\s+/g, ' ');
      const node = { kind: 'rule', prelude, items: [], bodyStart: i + 1, start: itemStart, end: -1 };
      top().items.push(node);
      stack.push(node);
      blocks.push(node);
      itemStart = i + 1; i++; continue;
    }
    if (c === '}') {
      pushStatement(itemStart, i);
      if (stack.length > 1) { const n = stack.pop(); n.bodyEnd = i; n.end = i + 1; }
      itemStart = i + 1; i++; continue;
    }
    i++;
  }
  return blocks;
}

/* -------------------------------------------------------------------- rangs */

function rankOf(rawValue) {
  const v = rawValue.replace(/!\s*important\s*$/i, '').trim();
  if (/(^|[^\w-])(?!var\s*\()[a-zA-Z-][\w-]*\s*\(/.test(v)) return 2;
  if (/#[0-9a-fA-F]{3,8}\b/.test(v)) return 2;
  if (/["']/.test(v)) return 2;
  if (/\bvar\s*\(/.test(v)) return 3;
  if (/(^|[\s,(/])-?(\d+\.?\d*|\.\d+)(%|[a-zA-Z]+)/.test(v)) return 4;
  if (/^-?(\d+\.?\d*|\.\d+)(\s+-?(\d+\.?\d*|\.\d+))*$/.test(v)) return 5;
  return 1;
}

/* --------------------------------------------------------------- collisions */

const SHORTHAND_LINKS = [
  ['inset', ['top', 'right', 'bottom', 'left']],
  ['gap', ['row-gap', 'column-gap']],
  ['place-items', ['align-items', 'justify-items']],
  ['place-content', ['align-content', 'justify-content']],
  ['place-self', ['align-self', 'justify-self']],
  ['grid-area', ['grid-row', 'grid-column']],
  ['font', ['line-height']],
  ['border-color', ['border-top-color', 'border-right-color', 'border-bottom-color', 'border-left-color']],
  ['border-style', ['border-top-style', 'border-right-style', 'border-bottom-style', 'border-left-style']],
  ['border-width', ['border-top-width', 'border-right-width', 'border-bottom-width', 'border-left-width']],
  ['border-radius', ['border-top-left-radius', 'border-top-right-radius', 'border-bottom-left-radius', 'border-bottom-right-radius']],
  ['background', ['background-color', 'background-image', 'background-position', 'background-size', 'background-repeat']],
];

function collides(a, b) {
  if (a === b) return true;
  if (a === 'all' || b === 'all') return true;
  if (a.startsWith(b + '-') || b.startsWith(a + '-')) return true;
  for (const [sh, longs] of SHORTHAND_LINKS) {
    if (a === sh && longs.some((l) => b === l || b.startsWith(l + '-'))) return true;
    if (b === sh && longs.some((l) => a === l || a.startsWith(l + '-'))) return true;
  }
  return false;
}

/* --------------------------------------------------------------------ordre */

function idealOrder(items) {
  const key = (a, b) => a.sortLen - b.sortLen || a.rank - b.rank || a.idx - b.idx;
  const up = items.filter((i) => i.rank === 1).sort(key);
  const pool = items.filter((i) => i.rank !== 1).sort(key);
  if (up.length) {
    let last = up[up.length - 1].sortLen;
    for (;;) {
      const pick = pool.findIndex((i) => i.sortLen > last);
      if (pick === -1) break;
      last = pool[pick].sortLen;
      up.push(pool.splice(pick, 1)[0]);
    }
  }
  pool.sort((a, b) => b.sortLen - a.sortLen || a.rank - b.rank || a.idx - b.idx);
  return [...up, ...pool];
}

function orderRun(run) {
  const items = run.map((it, idx) => ({ ...it, idx, rank: rankOf(it.value) }));
  const target = idealOrder(items);
  const priority = new Array(items.length);
  target.forEach((it, pos) => { priority[it.idx] = pos; });

  const n = items.length;
  const adj = Array.from({ length: n }, () => []);
  const indeg = new Array(n).fill(0);
  for (let a = 0; a < n; a++) {
    for (let b = a + 1; b < n; b++) {
      if (collides(items[a].prop, items[b].prop)) { adj[a].push(b); indeg[b]++; }
    }
  }
  const used = new Array(n).fill(false);
  const out = [];
  for (let k = 0; k < n; k++) {
    let best = -1;
    for (let i = 0; i < n; i++) {
      if (used[i] || indeg[i] !== 0) continue;
      if (best === -1 || priority[i] < priority[best]) best = i;
    }
    if (best === -1) return items.map((_, i) => i);
    used[best] = true;
    out.push(best);
    for (const j of adj[best]) indeg[j]--;
  }
  return out;
}

/* ---------------------------------------------------------------------runs */

// Une declaration n'est triable que si elle occupe sa ligne a elle seule :
// une regle ecrite sur une ligne unique n'est jamais reordonnee, seulement laissee telle quelle.
function isAlone(it, src) {
  if (!/^[ \t]*$/.test(src.slice(it.lineStart, it.start))) return false;
  const end = it.trailingCommentEnd ?? it.end;
  if (end > it.lineEnd) return false;
  return /^[ \t]*$/.test(src.slice(end, it.lineEnd));
}

function runsOf(node, src) {
  const runs = [];
  let cur = [];
  const flush = () => { if (cur.length > 1) runs.push(cur); cur = []; };
  for (const it of node.items) {
    if (it.kind !== 'decl' || it.multiline || !isAlone(it, src)) { flush(); continue; }
    if (cur.length) {
      const prev = cur[cur.length - 1];
      const between = src.slice(prev.lineEnd, it.lineStart);
      if ((between.match(/\n/g) || []).length > 1) flush();
    }
    cur.push(it);
  }
  flush();
  return runs;
}

/* ------------------------------------------------------------------analyse */

function analyze(src) {
  const eol = src.includes('\r\n') ? '\r\n' : '\n';
  const blocks = parse(src);
  const findings = [];
  const edits = [];

  for (const node of blocks) {
    // Un commentaire sur sa propre ligne entre deux declarations coupe le bloc :
    // les declarations de part et d'autre echappent alors au tri.
    node.items.forEach((it, i) => {
      if (it.kind !== 'comment') return;
      const before = node.items.slice(0, i).some((x) => x.kind === 'decl');
      const after = node.items.slice(i + 1).some((x) => x.kind === 'decl');
      if (!before || !after) return;
      findings.push({
        line: src.slice(0, it.start).split('\n').length,
        selector: node.prelude || '(racine)',
        badComment: true,
        from: it.start,
        to: it.end,
      });
    });

    for (const run of runsOf(node, src)) {
      const order = orderRun(run);
      if (order.every((v, i) => v === i)) continue;
      const from = run[0].lineStart;
      const to = run[run.length - 1].lineEnd;
      const texts = run.map((it) => src.slice(it.lineStart, it.lineEnd).replace(/\r$/, ''));
      findings.push({
        line: src.slice(0, from).split('\n').length,
        selector: node.prelude || '(racine)',
        expected: order.map((i) => texts[i]).join('\n'),
        badComment: false,
        from,
        to,
      });
      edits.push({ from, to, replacement: order.map((i) => texts[i]).join(eol) });
    }
  }
  edits.sort((a, b) => b.from - a.from);
  let out = src;
  for (const e of edits) out = out.slice(0, e.from) + e.replacement + out.slice(e.to);
  return { findings, output: out };
}

/* ---------------------------------------------------------------filesystem */

function walk(target, acc = []) {
  let st;
  try { st = statSync(target); } catch { return acc; }
  if (st.isDirectory()) {
    for (const name of readdirSync(target)) {
      const p = join(target, name);
      if (EXCLUDE.test(p + '/')) continue;
      walk(p, acc);
    }
  } else if (/^\.(css|scss)$/i.test(extname(target)) && !EXCLUDE.test(target)) {
    acc.push(target);
  }
  return acc;
}

function report(file, findings, cap = Infinity) {
  const lines = [];
  for (const f of findings.slice(0, cap)) {
    lines.push(`${file}:${f.line}  ${f.selector}`);
    if (f.badComment) {
      lines.push('  ! commentaire entre deux declarations : le bloc doit rester continu,');
      lines.push('    remonter le commentaire au-dessus du selecteur (fin de ligne tolere).');
      continue;
    }
    lines.push('  attendu :');
    for (const l of f.expected.split('\n')) lines.push('    ' + l.trim());
  }
  if (findings.length > cap) lines.push(`  (+${findings.length - cap} autres blocs non conformes)`);
  return lines.join('\n');
}

/* --------------------------------------------------------------------modes */

function editedRegions(toolInput, src) {
  if (!toolInput || typeof toolInput.content === 'string') return null;
  const needles = [];
  if (typeof toolInput.new_string === 'string') needles.push(toolInput.new_string);
  if (Array.isArray(toolInput.edits)) for (const e of toolInput.edits) if (e && e.new_string) needles.push(e.new_string);
  const regions = [];
  for (const n of needles) {
    const i = src.indexOf(n);
    if (i >= 0) regions.push([i, i + n.length]);
  }
  return regions.length ? regions : null;
}

async function runHook() {
  const chunks = [];
  for await (const c of process.stdin) chunks.push(c);
  const raw = Buffer.concat(chunks).toString('utf8');
  if (!raw.trim()) return 0;
  let data;
  try { data = JSON.parse(raw); } catch { return 0; }
  const file = data && data.tool_input && data.tool_input.file_path;
  if (!file || !/\.(css|scss)$/i.test(file) || EXCLUDE.test(file)) return 0;
  let src;
  try { src = readFileSync(file, 'utf8'); } catch { return 0; }

  const { findings } = analyze(src);
  if (!findings.length) return 0;
  const regions = editedRegions(data.tool_input, src);
  const touched = regions
    ? findings.filter((f) => regions.some(([a, b]) => f.from < b && f.to > a))
    : findings;
  if (!touched.length) return 0;

  process.stderr.write(
    '[css-order] ordre des declarations non conforme (sablier inverse) :\n' +
    report(file, touched, MAX_REPORTED_BLOCKS) + '\n'
  );
  return 2;
}

function runFiles(mode, paths) {
  const files = paths.flatMap((p) => walk(p));
  let bad = 0, changed = 0, failed = 0;
  for (const file of files) {
    const src = readFileSync(file, 'utf8');
    const { findings, output } = analyze(src);

    if (mode === 'audit') {
      const norm = (s) => s.split(/\r?\n/).map((l) => l.trim()).sort().join('\n');
      const problems = [];
      if (norm(src) !== norm(output)) problems.push('texte perdu ou altere');
      if (analyze(output).output !== output) problems.push('non idempotent');
      for (const node of parse(src)) {
        for (const run of runsOf(node, src)) {
          const order = orderRun(run);
          const pos = new Array(order.length);
          order.forEach((v, k) => { pos[v] = k; });
          for (let a = 0; a < run.length; a++) {
            for (let b = a + 1; b < run.length; b++) {
              if (!collides(run[a].prop, run[b].prop)) continue;
              if (pos[a] > pos[b]) problems.push(`collision inversee ${run[a].prop}/${run[b].prop}`);
            }
          }
        }
      }
      if (problems.length) { failed++; console.log(`FAIL ${file} : ${problems.join(', ')}`); }
      continue;
    }

    if (!findings.length) continue;
    bad++;
    if (mode === 'write') {
      if (output !== src) { writeFileSync(file, output, 'utf8'); changed++; }
      const comments = findings.filter((f) => f.badComment);
      if (comments.length) console.log(report(file, comments));
    } else {
      console.log(report(file, findings) + '\n');
    }
  }
  if (mode === 'audit') {
    console.log(`audit : ${files.length} fichiers, ${failed} echec(s)`);
    return failed ? 1 : 0;
  }
  if (mode === 'write') {
    console.log(`${changed}/${files.length} fichiers reordonnes`);
    return 0;
  }
  console.log(`${bad}/${files.length} fichiers non conformes`);
  return bad ? 1 : 0;
}

const [, , flag, ...rest] = process.argv;
if (flag === '--hook') process.exit(await runHook());
else if (flag === '--check' || flag === '--write' || flag === '--audit') process.exit(runFiles(flag.slice(2), rest));
else {
  console.log('usage: css-order.mjs --hook | --check <paths> | --write <paths> | --audit <paths>');
  process.exit(0);
}
