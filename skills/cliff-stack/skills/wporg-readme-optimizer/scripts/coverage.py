#!/usr/bin/env python3
"""coverage.py — Analyse DETERMINISTE de la couverture de mots-clés d'un readme.txt
WordPress.org par rapport à un jeu de requêtes cibles.

Modèle : le search du repo agrège titre + short description + tags + contenu dans un
champ `all_content` matché historiquement en AND (tous les mots de la requête doivent
apparaitre). Ce script verifie, pour chaque requête, quels mots sont couverts/manquants
et rend un verdict AND (INCLUS / EXCLU) + un score global. Voir
reference/wporg-search-algorithm.md pour la nuance datée AND vs OR (2025).

USAGE :
    python coverage.py <readme.txt|all_content.txt> <queries.txt>
    python coverage.py <readme.txt> --queries "animated gutenberg blocks" "scroll animation"

- <readme.txt> : chemin du readme (ou de n'importe quel texte représentant all_content).
- <queries.txt> : fichier texte, UNE requête cible par ligne (lignes vides / #commentaires ignorées).
  OU passer les requêtes en ligne via --queries.

Sortie : rapport lisible + JSON sur stdout (bloc final). Aucune dependance externe.

Limites assumées (déterministes, pas de magie) :
- Normalisation simple : minuscules, accents retirés, ponctuation -> espaces.
- Matching par mot entier sur l'ensemble du texte (le repo agrège les champs, on ne
  pondère pas par champ ici : ce script audite la COUVERTURE, pas le ranking).
- Pas de stemming agressif : on gère seulement un pluriel/singulier 's' naïf en option.
"""
import sys
import json
import re
import unicodedata

# Mots vides courants (EN + FR) qu'on n'exige pas dans le matching AND.
STOPWORDS = {
    "the", "a", "an", "and", "or", "of", "for", "to", "in", "on", "with", "your",
    "le", "la", "les", "un", "une", "de", "des", "du", "pour", "et", "ou", "en",
}


def strip_accents(s: str) -> str:
    return "".join(
        c for c in unicodedata.normalize("NFD", s) if unicodedata.category(c) != "Mn"
    )


def normalize(text: str) -> str:
    text = strip_accents(text.lower())
    text = re.sub(r"[^a-z0-9]+", " ", text)
    return re.sub(r"\s+", " ", text).strip()


def tokenize(text: str):
    return [t for t in normalize(text).split(" ") if t]


def naive_variants(word: str):
    """Variantes singulier/pluriel naïves pour limiter les faux négatifs."""
    v = {word}
    if word.endswith("s") and len(word) > 3:
        v.add(word[:-1])
    else:
        v.add(word + "s")
    return v


def load_queries(args):
    if "--queries" in args:
        i = args.index("--queries")
        return [q for q in args[i + 1:] if q.strip()]
    if len(args) < 2:
        return None
    path = args[1]
    out = []
    with open(path, encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith("#"):
                continue
            out.append(line)
    return out


def main():
    args = sys.argv[1:]
    if len(args) < 2:
        print(__doc__)
        sys.exit(1)

    readme_path = args[0]
    with open(readme_path, encoding="utf-8") as f:
        content_tokens = set(tokenize(f.read()))

    # élargir le vocabulaire du contenu avec les variantes naïves
    content_vocab = set(content_tokens)
    for tok in list(content_tokens):
        content_vocab |= naive_variants(tok)

    queries = load_queries(args)
    if not queries:
        print("Aucune requête fournie (queries.txt ou --queries).", file=sys.stderr)
        sys.exit(1)

    results = []
    for q in queries:
        words = [w for w in tokenize(q) if w not in STOPWORDS]
        covered, missing = [], []
        for w in words:
            if naive_variants(w) & content_vocab:
                covered.append(w)
            else:
                missing.append(w)
        n = len(words) or 1
        results.append({
            "query": q,
            "words": words,
            "covered": covered,
            "missing": missing,
            "coverage_pct": round(100 * len(covered) / n),
            "and_verdict": "INCLUS" if not missing else "EXCLU",
        })

    included = sum(1 for r in results if r["and_verdict"] == "INCLUS")
    summary = {
        "readme": readme_path,
        "queries_total": len(results),
        "queries_included_AND": included,
        "global_score_pct": round(100 * included / (len(results) or 1)),
    }

    # Rapport lisible
    print("=" * 64)
    print("COUVERTURE DE MOTS-CLES — readme.txt vs requetes cibles")
    print("=" * 64)
    for r in results:
        mark = "OK " if r["and_verdict"] == "INCLUS" else "!! "
        print(f"\n{mark}[{r['and_verdict']}] \"{r['query']}\"  ({r['coverage_pct']}%)")
        if r["missing"]:
            print(f"    MOTS MANQUANTS : {', '.join(r['missing'])}")
        else:
            print("    tous les mots couverts")
    print("\n" + "-" * 64)
    print(f"Requetes incluses (AND) : {included}/{len(results)} "
          f"= {summary['global_score_pct']}%")
    print("-" * 64)
    if included < len(results):
        print("NB: un 'EXCLU' peut etre un FAUX NEGATIF si le mot est couvert")
        print("    par un SYNONYME ou une forme flechie non geree (stemming naif).")
        print("    Verifier manuellement avant de sur-corriger le readme.")
    print("\nJSON:")
    print(json.dumps({"summary": summary, "results": results},
                     ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
