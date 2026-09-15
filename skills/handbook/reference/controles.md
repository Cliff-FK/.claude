# Les contrôles d'un corpus

Une règle sans oracle est une intention, pas une règle. La preuve est venue d'un corpus où la règle
la plus ancienne, écrite dès le premier jour, s'est cassée trois fois, sur trois pages, à trois
passes différentes. La cause n'était pas l'inattention : c'est qu'aucun contrôle ne la vérifiait,
là où les règles voisines avaient chacune le leur et arrêtaient la compilation.

**Toute règle de forme qui se mesure a vocation à rejoindre un contrôle plutôt qu'à rester en
prose.** Écrire la règle une seconde fois ne la tient pas ; la mesurer la tient.

## Ce qu'un contrôle doit faire

**Nommer le fichier et la ligne.** Un message qui dit qu'une expression est mal formée à la colonne
soixante-et-une d'une page qu'il n'affiche pas ne sert à rien. Le contrôle passe donc AVANT le
compilateur, pour dire la faute dans les mots de l'auteur.

**Arrêter la construction quand l'échec serait silencieux autrement.** Un composant qui lève sur une
clé inconnue et qu'un compilateur tolérant se contente d'écrire en avertissement laisse la
construction verte et la page amputée : le renvoi disparaît du rendu sans un mot. C'est le mode
d'échec à refuser. Le contrôle en amont refuse de compiler tant qu'une page cite une clé qui
n'existe pas.

**Tenir les deux sens.** Un média cité mais absent du catalogue arrête déjà la construction. Rien ne
dit en revanche qu'un média produit reste sur le disque sans qu'aucune page ne l'affiche, alors que
la table continue de le reproduire à chaque passe. Le sens inverse se relève aussi, et il trouve.

**Ne pas remplacer le réseau par une devinette.** Un contrôle qui interroge une source externe a
deux modes : hors ligne à la construction, sur ce que le dépôt contient déjà, et en ligne à la
demande, pour confronter le catalogue à sa source. Deviner un identifiant externe depuis un titre ne
marche pas : la moitié des formes plausibles répondent introuvable, et une adresse morte publiée
dans un manuel est pire que pas de lien.

## Ce qui mérite un contrôle

Par ordre de rentabilité mesurée sur un corpus réel.

**La syntaxe qui casse la compilation sans se nommer.** Les fautes d'écriture qui produisent un
message inutile se relèvent avant le compilateur : une espace typographique posée là où l'outil
attend une espace ordinaire, une apostrophe non échappée dans une chaîne.

**Les citations, contre leur source.** Voir la section sur la citation dans le skill : le texte cité
vit dans la page et tient lieu d'empreinte, un contrôle hors ligne dit lesquelles n'existent plus.

**La géométrie rendue de chaque média**, qui transforme « on n'y lit rien » en liste triée.

**La couverture d'un sujet**, c'est-à-dire les réglages qu'une page ne nomme pas alors que la source
du produit les expose. Le relevé se fait contre la source, jamais contre une liste tenue à la main,
qui divergerait au premier réglage ajouté. Il se fait **par sujet et non par page**, deux pages
pouvant se partager un même objet.

Ce relevé suppose une contrainte d'ÉCRITURE, pas d'outillage : **le lien d'une page vers le sujet
qu'elle documente se lit dans la page, jamais dans une table tenue à côté**, qui dériverait. Les
marqueurs qu'une page porte déjà pour d'autres raisons, le titre nommé dans sa scène d'accès, une
citation et son identifiant de source, le portent d'eux-mêmes ; une page qui n'en a aucun le déclare
explicitement, en une ligne invisible au rendu, sous son titre. Sans cette déclaration, l'oracle est
aveugle sur toute la part du corpus qui documente des greffes, et c'est justement là qu'il trouve :
sur un manuel relu, ce relevé a découvert un réglage majeur documenté nulle part et une page entière
qui décrivait une structure de panneau qui n'était pas celle du code.

**Les règles de mise en scène mesurables** : une capture posée hors de son composant de scène, un
schéma qui porte une phrase au lieu d'une étiquette, une page qui mêle deux provenances.

**Les liens et les ancres**, entrants comme sortants, une page sans lien entrant n'étant atteignable
que par recherche.

## Étalonner une sonde avant d'en tirer une conclusion

Une recherche textuelle sur du contenu compte des mentions, pas des faits. Deux faux positifs
mesurés valent d'être retenus, parce qu'ils ont l'un et l'autre failli produire une correction de
masse sur du texte correct.

Un relevé d'apostrophes non échappées en a compté cent vingt-deux, sur des lignes parfaitement
valides : son motif prenait une apostrophe déjà échappée pour une chaîne refermée. Le motif corrigé,
étalonné sur cinq cas connus, en a trouvé quatre, toutes réelles.

Un oracle de géométrie signalait les petites captures affichées à leur taille réelle, qui ne sont
pas un défaut : il ne devait compter que ce que le cadre réduit.

**Étalonner dans les deux sens.** Un cas connu-positif doit faire rougir le contrôle, un cas
connu-négatif doit le laisser muet. Une règle de provenance se vérifie ainsi : un terme posé hors
marqueur la fait rougir, le même sous marqueur ou dans une citation ne la réveille pas.

**Une sonde oriente, elle ne juge pas.** Un intitulé relevé comme absent d'une page est parfois un
titre de fenêtre d'aide ou l'état d'un écran de chargement, jamais un réglage. Le relevé se relit.

**Un relevé qui compte les exceptions connues gonfle.** Sur un corpus, les plages de texte sans
média comptaient cent quinze défauts dont quatre-vingt-quatre étaient l'ouverture ou la fin d'une
page, exceptions écrites. Exclure les exceptions dans le contrôle, pas dans la tête du lecteur du
rapport.

**Élargir une sonde qui n'a rien trouvé avant de conclure.** Un relevé de couverture a dû être
élargi trois fois, et chaque élargissement a trouvé : d'abord les objets enfants, puis les modules
greffés sur des objets de l'hôte, enfin les sources qui ne portent pas le nom de fichier attendu.
Une absence ne se prononce qu'après avoir cherché sur tout le périmètre où la chose peut vivre.

## Publier sans casser ce qui est en ligne

Un compilateur qui vide son dossier de sortie avant d'écrire laisse le site entier introuvable
pendant toute construction interrompue, et jusqu'à la suivante. Trois liens morts suffisent.
Construire à côté et ne remplacer qu'une fois la construction réussie : un échec laisse la version
précédente en ligne.
