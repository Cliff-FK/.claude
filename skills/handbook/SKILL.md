---
name: handbook
description: Architecture, rédaction et illustration d'une documentation destinée à un utilisateur NON DÉVELOPPEUR qui cherche à obtenir un comportement précis dans un produit. Trois régimes : architecturer, rédiger, illustrer. À utiliser quand on demande de concevoir, structurer, découper, classer, auditer, écrire, réviser ou illustrer un manuel, un guide, un tutoriel ou une base de connaissances utilisateur, de trancher un titre, un sommaire, un découpage de pages, la place ou le cadrage d'une capture ou d'un schéma, la façon de citer un texte de l'interface, ou l'oracle qui tiendra une règle de forme au moment de compiler. NE PAS utiliser pour une documentation destinée à des développeurs (README, doc d'API), pour l'aide contextuelle affichée dans une interface (texte d'aide d'un contrôle, infobulle, modale), qui obéit à ses propres contraintes de place, ni pour des pages générées depuis le code, qui ne s'écrivent jamais à la main.
---

# Architecturer, rédiger et illustrer une documentation

Le lecteur n'est pas développeur. Il arrive par un lien partagé ou une recherche, **il n'a rien sous
les yeux** : ni l'écran, ni le réglage, ni le contexte. C'est ce qui sépare une page de manuel d'un
texte d'aide affiché dans l'interface, et tout le reste en découle.

Trois régimes, dans cet ordre. **Architecturer** décide de ce qui existe, où, et sous quel nom.
**Rédiger** écrit une page dont la place est déjà décidée. **Illustrer** montre ce que la page dit,
et commence par montrer où l'on va. Écrire sans avoir tranché la première question produit un corpus
qui grossit sans se ranger ; illustrer sans avoir écrit produit des images qui commentent le vide.

Une quatrième chose traverse les trois : **une règle sans oracle est une intention**. Toute règle de
forme qui se mesure finit dans un contrôle branché sur la construction, pas dans un document de plus.
Voir `reference/controles.md`.

## 1. Découvrir les conventions du projet

Avant la première page d'un projet, repérer et ne jamais supposer :

le dossier du corpus et son découpage, ce que chaque dossier signifie, les niveaux d'accès s'il en
existe, les pages générées depuis le code qui ne s'éditent pas, le fichier de sommaire, et le guide
d'écriture déjà en place s'il y en a un.

Ces éléments vivent en général dans un document d'architecture posé à côté du corpus. Un dossier
nommé `reference` n'est pas forcément généré, un projet peut n'avoir aucun niveau d'accès, un autre
peut interdire les images. Lire avant d'écrire.

Quand le projet documente déjà l'aide contextuelle de son interface, en hériter : ses règles de fond
valent ici, ses contraintes de place ne suivent pas.

Sur la présentation, **ne surcharger un thème éprouvé que sur ce qu'il ne couvre pas, et avec une
raison écrite**. Une première tentative sur un corpus a produit cent vingt lignes de style maison
qui réinventaient, moins bien, la police, l'interligne, la mesure et l'accent que le thème donnait
déjà. Ce qu'un thème de documentation ne couvre presque jamais, et qui justifie d'écrire : la figure
et sa légende, la grille de médias, l'agrandissement, et les dispositifs de mise à l'écart du B6.

# A. Architecturer

## A1. Partir des besoins, pas du produit

L'erreur la plus fréquente est de calquer l'arborescence de la documentation sur celle du code, sur
le menu du produit ou sur la liste des fonctionnalités. Une documentation s'organise autour des
**questions que le lecteur se pose**, et la structure qui en résulte ne ressemble presque jamais à
celle du produit.

La matière première est donc la liste des questions réellement posées : demandes de support,
tickets, mêmes questions qui reviennent, endroits où les gens se trompent. À défaut, les
formulations que le lecteur emploierait lui-même, jamais celles de l'équipe qui a construit l'outil.

Une fonctionnalité que personne ne questionne n'a pas besoin d'une page. Une question posée dix fois
en mérite une, même si elle porte sur trois clics.

**Découper par objet manipulé, pas par fonctionnalité livrée.** Le lecteur cherche « mon bouton », pas
le nom du composant qui met une icône dedans. Une page par objet rassemble tout ce qu'on peut y faire,
quel que soit le nombre de fonctionnalités qui s'y croisent, et c'est ce qui fait qu'on la trouve.

**Une capacité transverse ne prend pas de page.** Elle n'existe que dans les objets où elle apparaît,
et personne ne la cherche pour elle-même : elle se décrit en contexte dans chacun, brièvement, et une
seule fois en entier dans la référence. Lui donner sa page produit un titre que le lecteur ne tape
jamais, et une explication détachée de tout usage.

## A2. Classer : les quatre quadrants

Diátaxis, un quadrant par page, jamais deux. Le mélange est la première cause de documentation
confuse.

| le lecteur… | quadrant |
|---|---|
| apprend le produit, n'a pas de tâche précise | tutoriel |
| a une tâche précise et veut la finir | how-to |
| cherche un fait, une valeur, une liste | reference |
| veut comprendre pourquoi c'est ainsi | explanation |

Les deux axes qui les séparent : **agir ou savoir**, **pendant l'apprentissage ou pendant le
travail**. Un lecteur au travail ne veut pas apprendre, un lecteur qui apprend ne veut pas d'une
liste exhaustive.

**Les guides restent sélectifs, la référence couvre tout.** C'est ce qui concilie une exigence
d'exhaustivité, quand elle existe, avec le refus de documenter un besoin que personne n'a. Et quand
un même élément se retrouve sur plusieurs objets, la référence s'organise par élément et non par
objet : l'inverse le duplique autant de fois qu'il apparaît, et les copies divergent.

**Un guide écrit avant la référence a dû couvrir**, et porte souvent un tableau exhaustif que rien
d'autre ne portait. Le jour où la référence arrive, ce tableau ne se recopie pas et ne se laisse pas
non plus en double : il descend dans la référence, et le guide garde le renvoi plus les deux ou
trois valeurs dont le geste courant a besoin. Repérer ces sections avant d'écrire la référence, sans
quoi elle naît déjà en doublon d'un guide.

Une page générée depuis le code ne se corrige pas dans le corpus, où la prochaine génération
l'écrasera, mais à sa source.

## A3. Découper : une page, un besoin

Scinder quand la page répond à « comment » et à « pourquoi » dans le même souffle, ou quand le
lecteur venu pour la seconde moitié doit traverser une première moitié qui ne le concerne pas.

Un « et » dans le titre est un indice, pas une preuve : « créer et organiser vos pages » décrit un
geste continu, « styliser un tableau et changer les puces d'une liste » en décrit deux qui ne se
croisent jamais. Le test porte sur les besoins, pas sur la conjonction.

Fusionner quand deux pages ne se consultent jamais l'une sans l'autre : la coupure est alors
artificielle et coûte un aller-retour à chaque lecture.

**Le second besoin se formule-t-il sans nommer le premier ?** C'est le test qui tranche. « Mon titre
est caché derrière le menu » ne nomme pas l'en-tête : celui qui le cherche ignore la cause, donc il
lui faut sa page, sous son symptôme. « Ouvrir la fenêtre depuis un lien » nomme déjà la fenêtre : le
lecteur est arrivé par elle, une section suffit, et une page de plus ne ferait que répéter le titre
de sa voisine. Fusionner quand le second nomme le premier **et** tient en une section ; deux pages
qui se citent quand il demande un chantier à lui.

Un sujet qui déborde ne se règle pas en allongeant la page mais en la scindant et en faisant se
citer les morceaux. Une page longue est acceptable dans le quadrant explanation, suspecte dans un
how-to.

**Au-delà de deux dépendances, une explication.** Une fonctionnalité qui en met deux autres en jeu
appelle une page qui montre le montage d'ensemble. Sans elle, le lecteur assemble à l'aveugle en
lisant trois how-to qui ne se parlent pas, et chacun d'eux a pourtant l'air complet.

## A4. Nommer et ordonner

Le titre porte les mots du lecteur, pas le nom technique. Quelqu'un cherche « filtrer une liste »,
pas l'identifiant du composant. Le nom technique vit dans le corps de la page, où il est cherché
comme tel, et dans la référence.

Le sommaire s'ordonne **du générique vers le spécialisé**, jamais alphabétiquement et jamais dans
l'ordre du code. Ce qui concerne tout le monde et tout le temps l'ouvre, ce qui ne concerne qu'une
partie des lecteurs le referme. La règle vaut aussi **à l'intérieur de chaque groupe** : le geste
courant avant la finition.

C'est aussi l'ordre d'apprentissage : personne ne filtre un listing avant d'avoir réglé les couleurs
de son site. La fréquence d'usage mesurée corrige ce classement quand on finit par en disposer, elle
ne le remplace pas, et elle n'existe pas au démarrage.

Les titres sont **stables** : ils portent les ancres partagées par lien. Reformuler un titre casse
des liens déjà envoyés, ce qui suppose de router l'ancien dans le même geste.

## A5. Se greffer sur un hôte

Un produit qui s'ajoute à l'interface d'un hôte, plugin, thème ou extension, pose un problème que sa
seule documentation ne résout pas : le lecteur voit côte à côte, sans distinction visuelle, des
réglages de l'hôte et des réglages du produit. Quand quelque chose résiste, il ne sait même pas à
qui poser sa question.

La documentation explique donc **les bases de l'hôte nécessaires pour s'en servir**, placées en tête
de leur propre groupe, et renvoie à la documentation de l'hôte pour l'exhaustivité. Elle ne les
rassemble jamais dans un dossier « bases » à part, qui obligerait à l'aller-retour sur un même
sujet.

Ce que personne d'autre ne peut écrire, et qui justifie la page, c'est **où s'arrête l'hôte et où
commence le produit**. De l'hôte, n'écrire que le stable, les notions et les gestes, jamais la
position d'un bouton à l'écran, qui change à chaque version.

## A6. Séparer les provenances

Un produit qui se livre en couches, un socle et des extensions optionnelles, pose la règle des
quadrants une seconde fois, appliquée cette fois à la **provenance**. Une page explique le socle, ou
elle explique une extension, jamais les deux au fil du même texte.

Les conséquences sont les mêmes qu'un mélange de quadrants, et plus difficiles à voir. Le lecteur
dont l'installation n'a pas l'extension cherche des réglages qui n'existeront jamais chez lui et
croit à son erreur ; celui qui l'a ne sait plus ce que le socle garantit le jour où il change de
contrat. Aucun des deux ne peut voir depuis la page laquelle des deux situations est la sienne.

**La forme par défaut est une page dédiée, rangée dans la section où le sujet vit**, pas dans un
groupe nommé d'après l'extension : le lecteur cherche par tâche, pas par origine du code. La page du
socle la cite en une ligne.

**Une section dédiée est le repli**, pour la matière qui ne porte pas une page : une origine de plus
dans une liste, une ligne de tableau, un paragraphe. Elle s'annonce dès son titre, se tient d'un
seul tenant, et **dit à sa première phrase ce qu'elle suppose**, par un marqueur que le lecteur
reconnaît d'une page à l'autre.

Reste admis, et ne compte pas pour un mélange : nommer une extension en passant, s'en servir pour un
exemple, constater qu'elle peut ajouter quelque chose, renvoyer vers sa page. La règle porte sur
l'explication d'un fonctionnement, pas sur le mot.

**Ce découpage n'est pas qu'une commodité de lecture** dès que le corpus se sert à des lecteurs qui
n'ont pas tous les mêmes modules : une page pure se retire du service et du sommaire quand le module
manque, une page mixte est justement celle qu'on ne peut pas retirer, puisqu'elle porte aussi ce que
tout le monde a. Le mélange coûte alors la seule mesure qui protège vraiment le lecteur.

## A7. Mailler

Chaque how-to renvoie vers la référence de ce qu'il manipule et vers l'explication du pourquoi, s'il
y en a une. Chaque explication renvoie vers les tâches qu'elle éclaire.

Une page sans lien entrant n'est atteignable que par recherche, donc à moitié invisible. La repérer
est un test simple de santé du corpus.

**Un lien interne part de la racine du corpus, jamais du fichier qui le porte.** Beaucoup de rendus
ne résolvent pas les `../` : ils les concatènent, et le lien mène à une adresse qui n'existe pas. Un
lien qui ne dépend pas de l'emplacement de sa page survit aussi à son déplacement.

## A8. Créer un dossier

Seulement quand un quadrant compte assez de pages pour qu'on s'y perde. Un dossier d'une page est un
dossier de trop, et un niveau de profondeur en plus est un niveau que le lecteur doit deviner.

## A9. Auditer un corpus existant

Six symptômes, du plus grave au plus bénin :

une page qui mélange deux quadrants ; une page fourre-tout, reconnaissable à son titre générique
(« Configuration », « Divers ») ; deux pages qui expliquent la même chose et qui ont déjà divergé ;
une page qu'aucune autre ne cite ; un titre écrit en vocabulaire technique ; une page que personne
n'ouvre jamais, qui documente soit un besoin inexistant, soit un besoin mal nommé.

L'audit vise les pages **et les groupes**. Un groupe fourre-tout se reconnaît à ce qu'il rassemble
des natures différentes sous un mot vague : un dossier « navigation » contenant une fenêtre modale,
un menu d'ancres et une carte mélange de la présentation, de la navigation et du contenu embarqué.
Le symptôme est celui d'une page fourre-tout, et il coûte plus cher, puisqu'il égare avant même
l'ouverture d'une page.

**Le doublon de sujet est le symptôme le plus rentable**, même s'il n'est pas le plus grave. Deux ou
trois pages qui traitent le même sujet à des niveaux de détail différents obligent le lecteur à
choisir laquelle croire, et elles ont toujours déjà divergé. Une refonte mesurée sur un corpus a
réduit un chapitre de six pages à deux, et trois signes disent qu'une fusion est la bonne décision :
la page fusionnée ne s'allonge pas, elle raccourcit, le « pourquoi » d'un geste rejoignant la page
qui montre ce geste ; chaque fusion fait apparaître une erreur que la coexistence des deux versions
masquait, et c'est là son vrai rendement ; et la page qui survit est celle vers laquelle va le
lecteur, pas la plus détaillée. Sur ce corpus, la page détaillée disait vrai et la page de geste
mentait, ce qui ne se serait jamais vu sans la fusion.

L'audit produit une liste de décisions (scinder, fusionner, renommer, router, supprimer), pas une
réécriture immédiate. Chaque renommage entraîne un alias.

Un audit fabrique des sondes, et c'est là qu'on se fabrique des défauts imaginaires : **étalonner
toute sonde sur un cas connu-positif et un cas connu-négatif avant d'en tirer une décision**. Voir
`reference/controles.md`.

## A10. Citer un texte produit ailleurs

Une documentation qui reprend un texte produit ailleurs, libellé d'interface, message d'erreur ou
valeur par défaut, devient fausse le jour où ce texte change, et rien ne le signale.

**La citation tient lieu d'empreinte.** Écrite dans la page et versionnée avec elle, elle se compare
à sa source par un simple contrôle hors ligne, qui liste les pages dont le texte cité n'existe plus.
Rien n'est conservé à part, et c'est le dispositif principal, parce qu'il ne peut pas nuire.

Le texte cité s'écrit dans la page, il ne s'y substitue jamais à la volée : une documentation qui
exécute quelque chose au rendu se met à dépendre d'un outil pour être lue. La **zone citée** est donc
une convention d'écriture, encadrée et identifiée comme un extrait, où le lecteur sait qu'il lit
l'interface et non une explication. Fondu dans la prose, le même texte ment, puisqu'il a été écrit
pour quelqu'un qui voyait l'écran.

La synchronisation est **descendante et tolérante**. La source fait foi, jamais la doc. Une source
disparue produit un signalement plutôt qu'une page cassée. Et elle consomme des identifiants qui
existent déjà pour d'autres raisons : le jour où elle en réclame un dédié, c'est le couplage qui est
mal posé, pas le produit qui doit s'adapter à sa documentation.

La lecture de la source se fait **en amont**, au moment de produire la doc, jamais au moment de
l'afficher : sinon la documentation dépend du système documenté et varie d'un lecteur à l'autre.

Trois règles bornent ce qui est citable, et chacune a été payée par une panne.

**On ne cite que ce dont on possède la chaîne source.** Un libellé de l'hôte est une chaîne écrite
dans sa langue d'origine puis traduite à l'affichage : la citer met une langue étrangère dans le
manuel, et citer sa traduction rend la citation invérifiable, le contrôle ne cherchant que dans les
sources du produit documenté. Ce qui vient de l'hôte se décrit, il ne se cite pas.

**Un texte qui contient du balisage ne se cite pas.** Selon le compilateur, une balise trouvée dans
une citation est prise pour du balisage réel et fait échouer la construction du corpus entier, pas
seulement de sa page. L'échapper romprait l'empreinte, que le contrôle cherche caractère pour
caractère. Ces textes se décrivent, comme ceux de l'hôte.

**Une citation se copie depuis le fichier source, jamais à la main ni depuis un rendu.** Les chaînes
portent des espaces insécables et des apostrophes typographiques qu'une saisie perd sans qu'on le
voie, et un contrôle qui signale l'écart « à la ponctuation près » avertit sans échouer : une
citation approximative passe donc inaperçue.

# B. Rédiger

## B1. Vérifier avant d'affirmer

Une page décrit un comportement réel. Elle se vérifie dans le code du projet ou sur le produit
servi, jamais de mémoire ni par analogie avec un produit voisin.

Le nom d'un réglage se relève là où il est affiché, à l'identique. Un comportement se vérifie à
l'exécution, pas par lecture du code seul. Une limite se prouve avant d'être écrite : « sans effet
si… » est une affirmation, pas une précaution.

Ce relevé à l'écran sert à **identifier** le bon réglage, il ne sert jamais de source à une citation.
Les deux gestes ne se confondent pas : le nom se lit là où le lecteur le verra, la citation se copie
dans le fichier source, seule vérifiable. Voir A10.

Ce qui ne peut pas être vérifié ne s'écrit pas. Une page incomplète mais juste vaut mieux qu'une
page complète et fausse : le lecteur ne peut pas distinguer les deux, et c'est lui qui paiera
l'erreur.

**Quatre causes produisent l'essentiel des erreurs de fait**, relevées sur une passe où six critiques
indépendants ont attaqué un même lot de pages. Elles valent d'être cherchées nommément, parce
qu'aucune ne se voit à la relecture.

*Un commentaire de code n'est pas une preuve.* Une borne annoncée dans un commentaire, une
explication de repli dans une feuille de style : les deux étaient périmées, et les deux avaient été
recopiées dans une page. Un commentaire dit ce que quelqu'un croyait vrai le jour où il l'a écrit.

*Une garantie se prouve sur la chaîne entière.* « Le style suit toujours l'objet » tombe dès que
l'objet n'apparaît sur aucun résultat de la première page d'une liste. Une affirmation universelle
se teste sur le maillon le plus faible, pas sur le cas nominal.

*Le générique n'est pas le particulier.* Le comportement de l'hôte est souvent bien décrit et celui
de l'installation documentée faux : modèles, menus, structure des adresses. Un fait sur cette
installation se mesure sur cette installation.

*Un correctif se propage au sujet, pas à la page.* Ajouter un renvoi sans retirer la limite qu'il
contredit crée une contradiction interne ; faire passer une énumération de deux à trois causes sur
une page laisse sa voisine à deux. Après toute correction, relever les pages qui traitent le même
sujet et vérifier qu'aucune ne dit encore l'ancienne version.

## B2. Les règles d'écriture

**Situer en ouverture.** Deux phrases : de quoi on parle, et quand on en a besoin. Le lecteur arrive
sans contexte, contrairement à celui qui lit une aide affichée à côté de son réglage.

**Nommer les éléments tels qu'ils sont affichés**, à l'identique. Le lecteur doit relier la phrase à
ce qu'il voit à l'écran.

**Dire les limites connues** plutôt que les taire. C'est ce que le lecteur vient chercher quand son
réglage ne fait rien.

**La tâche d'abord, les limites après.** Divulgation progressive : ce qu'on est venu chercher en
premier, les cas particuliers ensuite, repliés quand ils sont longs. Une limite en tête de page
bloque un lecteur qui n'y était pas exposé.

**Un exemple concret par idée**, montrant ce que le lecteur voit ou recopie : une valeur, une
adresse, un identifiant. Jamais un nom d'attribut ou de classe interne, il n'a pas à les taper.

**Pas de vocabulaire d'implémentation.** Décrire l'effet visible ; une contrainte technique se traduit
en conséquence pour le lecteur.

**Renvoyer plutôt que redire.** Une explication recopiée à deux endroits diverge au premier
changement.

## B3. La forme du texte

**Le gras porte la scannabilité, pas la puce.** Le lecteur repère les notions importantes en gras
dans un texte suivi. Une liste à puces ne se justifie que si son contenu EST une énumération,
c'est-à-dire si chaque item se tient seul et n'a besoin d'aucun connecteur pour se relier au
précédent : des limites connues, des valeurs possibles, des étapes numérotées. Partout ailleurs la
puce supprime les connecteurs (donc, sauf si, parce que) et transforme un raisonnement en
inventaire. Dans le doute, écrire le paragraphe.

Deux sections trompent, et se sont écrites en prose sur tout un corpus relu : **les prérequis**, qui
enchaînent des conditions dont chacune explique la suivante, et **les renvois finaux**, où chaque
lien se donne avec la raison d'aller le voir. La quasi-totalité des listes d'un manuel tiennent en
fait dans une seule section, celle des limites connues, où les items sont vraiment indépendants.

Les titres de section portent l'information, jamais « Introduction » ni « Généralités ».

**Un titre annonce une section, pas une phrase.** Un titre dont le corps tient en une phrase n'est
pas une section : cette phrase rejoint le chapeau de la page ou la section voisine. La seule
exception est le bloc de renvois final, dont la brièveté est la fonction.

**Un paragraphe porte une idée développée**, pas une phrase isolée. Le retour à la ligne se mérite :
il marque un changement d'idée, jamais une respiration. Un texte haché en micro-blocs perd la
hiérarchie de ses idées aussi sûrement qu'une liste à puces.

Le défaut n'est pas la brièveté, c'est la **fragmentation**. Un paragraphe d'une seule phrase est
bon quand cette phrase EST l'idée ; trois phrases qui développent le même point, séparées par deux
blancs, se lisent trois fois moins bien qu'un paragraphe de trois phrases. C'est la règle du guide de
style technique de Google, qui admet explicitement le paragraphe d'une seule phrase et fixe la borne
haute : au-delà de cinq ou six phrases, un paragraphe en porte probablement plusieurs. Ce défaut se mesure, et il
surprend : sur un corpus relu, 45 % des paragraphes tenaient en une phrase, et quatorze sections de
prérequis en une ligne. Il se relève donc page par page à la relecture, pas seulement à la
rédaction.

**Un paragraphe qui ouvre sur un membre en gras, répété en enfilade, est une liste déguisée.** Cent
trente-neuf paragraphes de cette forme, alignés deux par deux avec du blanc entre eux, sont devenus
des listes dans un même corpus, et deux énumérations sont devenues des tableaux. La règle du gras
n'interdit pas la liste, elle interdit la liste décorative : une énumération vraie s'écrit comme
une énumération.

**Ni ligne, ni mot, ni ponctuation orpheline** en fin de paragraphe, ni titre qui casse sur un mot
seul. Le cas le plus fréquent est le paragraphe qui se termine par un lien long suivi d'un point : le
lien remplit la ligne et la ponctuation tombe seule à la suivante. Dans la prose de geste, ne pas
finir sur un lien, ou lui donner quelques mots après. La règle ne porte pas sur le paragraphe de
renvoi, dont le lien EST la fonction et qui se termine par lui par construction : mesuré sur un
corpus, les deux tiers des paragraphes finissant sur un lien étaient des renvois, et les corriger
aurait consisté à leur ajouter des mots pour rien.

Cela se règle à la rédaction. Le rendu ne fait que le second tour, avec `text-wrap: pretty`, et les
propriétés CSS `orphans` et `widows` n'y servent à rien : elles ne s'appliquent qu'aux médias
paginés.

## B4. Forme d'une page

Un **how-to**, le cas le plus fréquent, ouvre sur deux phrases de situation, puis les prérequis,
puis les étapes numérotées avec une action par étape, puis les limites connues, puis les renvois.

Un **tutoriel** prend la même forme, mais le lecteur ne choisit rien, il suit : un seul chemin,
aucune alternative, aucun « selon vos besoins ».

**Un tutoriel tient un outil et un domaine.** Un parcours dont les étapes traversent quatre écrans
sans rapport n'a de commun que d'être fait au début : c'est un fourre-tout, et son titre ment sur ce
qu'on y trouve. Ce qui déborde sort en renvois vers les guides, qui existent pour traiter les cas et
les limites. **La difficulté suit un gradient**, à l'intérieur d'une page comme dans le sommaire :
une étape qui suppose des notions que le parcours n'a pas posées n'est pas une étape avancée, c'est
une explication égarée dans un tutoriel. Elle part, et un renvoi la remplace.

Une **explanation** n'a pas d'étapes. Le sujet, le pourquoi de la décision, ce que cela implique.
Elle a le droit d'être longue, c'est sa nature.

## B5. Langue

Français, orthographe et accents complets. Phrases qui se lisent à voix haute. Voix active, deuxième
personne pour les instructions : « ouvrez le panneau », pas « le panneau doit être ouvert ».

Pas de marqueurs de rédaction automatique : ni tiret cadratin ou demi-cadratin en ponctuation
(virgule, deux-points ou parenthèses à la place), ni émoji, ni icône décorative, ni gras emphatique
gratuit. Le gras sert à repérer une notion, jamais à insister.

Typographie française : espace insécable avant les deux-points, points-virgules, points
d'interrogation et d'exclamation, et à l'intérieur des guillemets. Un signe double ne tombe jamais
seul en début de ligne.

**La règle typographique s'arrête au bord de ce qui n'est pas de la prose**, et trois zones y
échappent pour trois raisons distinctes. Une citation reprend un texte du produit mot pour mot, et
la retoucher ferait échouer son contrôle de dérive. Un bloc de code n'est pas de la prose. Et un
attribut de composant cesse d'être reconnu si l'espace qui le précède change de nature : ce
troisième cas s'est mesuré après coup, sur cent vingt-neuf attributs abîmés d'un seul geste, et il
mérite un contrôle de syntaxe branché avant le compilateur, dont le message, lui, ne nomme jamais la
cause.

## B6. Sortir du fil de lecture

Ce qui éclaire sans faire partie du geste sort du fil. Un lecteur qui suit une marche à suivre n'a
pas à traverser trois paragraphes d'architecture pour atteindre l'étape suivante, mais ces
paragraphes ont leur valeur pour qui veut comprendre le choix. Deux dispositifs, et pas un de plus.

**L'aparté** annonce le sujet en une phrase et ouvre le détail à la demande. Le composant est
générique, titre et résumé en paramètres, le détail en contenu : le point destiné aux développeurs
n'est qu'un de ses usages. **Sa composition dit à qui il s'adresse**, une chasse fixe se reconnaissant
avant d'être lue et se sautant sans effort, ce qui est aussi sa limite : une chasse fixe se lit plus
lentement, donc un aparté qui dépasse quelques paragraphes se scinde plutôt que de s'allonger.

**Le renvoi vers la documentation de l'hôte** tient la promesse de l'A5 sans allonger une phrase :
une icône en fin de paragraphe nomme l'article officiel, le résume en une ligne et l'ouvre. Trois
invariants le rendent tenable. **Une page ne connaît qu'une clé, jamais une adresse** : un catalogue
porte adresse, titre et résumé, un article renommé ne touche que le catalogue, et le même renvoi posé
dans dix pages ne s'écrit qu'une fois. **Les identifiants ne se devinent pas**, ils se lisent à la
source, la moitié des formes plausibles construites depuis un titre répondant introuvable. **C'est
une porte, jamais une étape** : le lecteur doit pouvoir ignorer toutes les icônes du corpus d'un bout
à l'autre sans rester bloqué, ce qui autorise la parcimonie, deux ou trois par page, et ce qui
l'exige. **Il se pose dans une phrase de prose**, jamais dans un titre, un tableau, une citation, un
bloc de code ou un attribut, où il n'a rien à ouvrir et casse ce qui le porte : c'est un contrôle,
pas une consigne.

Les deux dispositifs partagent la même mécanique de fenêtre, native et unique pour tout le corpus.
Un troisième mécanisme de mise à l'écart se paie en apprentissage pour le lecteur et en cas
particuliers à l'impression : avant d'en ajouter un, vérifier que l'un des deux ne fait pas l'affaire.

**Ce qui sort du fil ne disparaît pas du papier.** Aparté comme renvoi s'écrivent une fois et se
lisent sur tous les supports, à l'écran, à l'impression et dans un document assemblé où aucun
composant n'est monté. Le vérifier avant de multiplier les dispositifs : chacun se paie trois fois.

# C. Illustrer

Ne s'applique qu'à un corpus qui montre des écrans. Le détail, les huit sections de règles et les
pièges de production, vit dans `reference/illustrer.md`, à charger avant de poser ou de refaire le
moindre média. Ce qui suit est ce qu'il faut savoir avant même d'ouvrir ce fichier.

**Le premier média d'une page montre COMMENT ATTEINDRE le sujet, jamais ce qu'il fait.** C'est le
reproche qui a porté sur cinquante pages d'un même manuel. Un panneau photographié hors de son
contexte suppose que le lecteur sait déjà l'atteindre, ce qui est exactement ce qu'il ignore, et un
schéma tracé ne peut pas ouvrir une page puisqu'il explique un principe à qui sait déjà où regarder.
L'accès n'est pas un média de plus : il est le premier temps de la scène qui montre le sujet.

**Deux familles de pages n'ont pas d'accès, et ne s'en inventent pas** : celles qui expliquent un
mécanisme et ne mènent nulle part, et celles dont le sujet est transverse, dont l'accès est le
panneau qu'elles montrent déjà. À l'inverse, une page dont aucune scène n'accueillerait l'accès en
reçoit une dont l'accès est le seul encart : c'est la forme normale, pas un pis-aller.

**Un geste se montre en deux temps.** La porte puis la pièce, le menu puis l'écran, l'insertion puis
le panneau. Une scène qui s'arrête à la porte n'apprend rien de plus qu'une phrase, et un réglage à
plusieurs modes demande un temps par mode.

**Une page n'écrit jamais le balisage d'un média** : elle appelle un composant et ne nomme que son
sujet, le nom du fichier se déduisant des deux côtés.

**La lisibilité d'une image se calcule, elle ne se juge pas à l'œil.** Un cadre réduit une image
verticale par sa hauteur, et sa largeur rendue tombe avec : une capture irréprochable en fichier
s'affiche à soixante points de large. C'est un calcul, donc c'est un contrôle.

**Une capture d'un écran réel ne montre pas de vrais contenus.** Titres, noms, identifiants,
adresses se remplacent par des exemples, à la toute fin de la prise.

# Relire

La table des interdits, une ligne par règle sous la forme sous laquelle on la voit tomber, avec le
prix payé : `reference/interdits.md`. À passer sur une page finie, un plan de corpus ou un audit.
