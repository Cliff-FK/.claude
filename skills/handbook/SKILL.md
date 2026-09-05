---
name: handbook
description: Architecture et rédaction d'une documentation destinée à un utilisateur non développeur qui cherche à obtenir un comportement précis dans un produit. Deux régimes. Architecturer un corpus, en partant des besoins du lecteur et non de l'arborescence du produit, avec Diátaxis pour classer, un découpage une page pour un besoin, un sommaire ordonné du générique vers le spécialisé, les bases de l'hôte expliquées en tête de chaque groupe quand le produit se greffe sur un autre, un maillage explicite, et l'audit des pages comme des groupes. Rédiger une page une fois sa place décidée, en vérifiant tout comportement avant de l'affirmer, avec un titre formulé comme la requête du lecteur, la tâche avant les limites, et un texte suivi où le gras porte la scannabilité à la place des listes à puces. Découvre les conventions du projet plutôt que de les supposer. À utiliser quand on demande de concevoir, structurer, découper, classer, auditer, écrire ou réviser une documentation, un manuel, un guide ou une base de connaissances utilisateur. NE PAS utiliser pour l'aide contextuelle affichée dans une interface (texte d'aide d'un contrôle, infobulle, modale), qui obéit à ses propres contraintes de place, ni pour des pages générées depuis le code, qui ne s'écrivent jamais à la main.
---

# Architecturer et rédiger une documentation

Le lecteur n'est pas développeur. Il arrive par un lien partagé ou une recherche, **il n'a rien sous
les yeux** : ni l'écran, ni le réglage, ni le contexte. C'est ce qui sépare une page de manuel d'un
texte d'aide affiché dans l'interface, et tout le reste en découle.

Deux régimes. **Architecturer** décide de ce qui existe, où, et sous quel nom. **Rédiger** écrit une
page dont la place est déjà décidée. Écrire sans avoir tranché la première question produit un
corpus qui grossit sans se ranger.

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

## A6. Mailler

Chaque how-to renvoie vers la référence de ce qu'il manipule et vers l'explication du pourquoi, s'il
y en a une. Chaque explication renvoie vers les tâches qu'elle éclaire.

Une page sans lien entrant n'est atteignable que par recherche, donc à moitié invisible. La repérer
est un test simple de santé du corpus.

**Un lien interne part de la racine du corpus, jamais du fichier qui le porte.** Beaucoup de rendus
ne résolvent pas les `../` : ils les concatènent, et le lien mène à une adresse qui n'existe pas. Un
lien qui ne dépend pas de l'emplacement de sa page survit aussi à son déplacement.

## A7. Créer un dossier

Seulement quand un quadrant compte assez de pages pour qu'on s'y perde. Un dossier d'une page est un
dossier de trop, et un niveau de profondeur en plus est un niveau que le lecteur doit deviner.

## A8. Auditer un corpus existant

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

L'audit produit une liste de décisions (scinder, fusionner, renommer, router, supprimer), pas une
réécriture immédiate. Chaque renommage entraîne un alias.

## A9. Citer un texte produit ailleurs

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

# B. Rédiger

## B1. Vérifier avant d'affirmer

Une page décrit un comportement réel. Elle se vérifie dans le code du projet ou sur le produit
servi, jamais de mémoire ni par analogie avec un produit voisin.

Le nom d'un réglage se relève là où il est affiché, à l'identique. Un comportement se vérifie à
l'exécution, pas par lecture du code seul. Une limite se prouve avant d'être écrite : « sans effet
si… » est une affirmation, pas une précaution.

Ce qui ne peut pas être vérifié ne s'écrit pas. Une page incomplète mais juste vaut mieux qu'une
page complète et fausse : le lecteur ne peut pas distinguer les deux, et c'est lui qui paiera
l'erreur.

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
dans un texte suivi. Une liste à puces ne se justifie que si les items n'ont vraiment aucun lien
logique entre eux : prérequis, valeurs possibles, étapes ordonnées. Partout ailleurs elle supprime
les connecteurs (donc, sauf si, parce que) et transforme un raisonnement en inventaire. Dans le
doute, écrire le paragraphe.

Les titres de section portent l'information, jamais « Introduction » ni « Généralités ».

**Un paragraphe porte une idée développée**, pas une phrase isolée. Le retour à la ligne se mérite :
il marque un changement d'idée, jamais une respiration. Un texte haché en micro-blocs perd la
hiérarchie de ses idées aussi sûrement qu'une liste à puces.

**Ni ligne, ni mot, ni ponctuation orpheline** en fin de paragraphe, ni titre qui casse sur un mot
seul. Le cas le plus fréquent est le paragraphe qui se termine par un lien long suivi d'un point : le
lien remplit la ligne et la ponctuation tombe seule à la suivante. Ne pas finir sur un lien, ou lui
donner quelques mots après.

Cela se règle à la rédaction. Le rendu ne fait que le second tour, avec `text-wrap: pretty`, et les
propriétés CSS `orphans` et `widows` n'y servent à rien : elles ne s'appliquent qu'aux médias
paginés.

## B4. Forme d'une page

Un **how-to**, le cas le plus fréquent, ouvre sur deux phrases de situation, puis les prérequis,
puis les étapes numérotées avec une action par étape, puis les limites connues, puis les renvois.

Un **tutoriel** prend la même forme, mais le lecteur ne choisit rien, il suit : un seul chemin,
aucune alternative, aucun « selon vos besoins ».

Une **explanation** n'a pas d'étapes. Le sujet, le pourquoi de la décision, ce que cela implique.
Elle a le droit d'être longue, c'est sa nature.

## B5. Langue

Français, orthographe et accents complets. Phrases qui se lisent à voix haute. Voix active, deuxième
personne pour les instructions : « ouvrez le panneau », pas « le panneau doit être ouvert ».

Pas de marqueurs de rédaction automatique : ni tiret cadratin ou demi-cadratin en ponctuation
(virgule, deux-points ou parenthèses à la place), ni émoji, ni icône décorative, ni gras emphatique
gratuit. Le gras sert à repérer une notion, jamais à insister.

Typographie française : espace insécable avant les deux-points, points-virgules, points
d'interrogation et d'exclamation, et à l'intérieur des guillemets.

# Interdits

| interdit | conséquence |
|---|---|
| Calquer la structure du corpus sur celle du produit ou du code | le lecteur cherche par besoin, pas par module |
| Mélanger deux quadrants dans une page | le lecteur pressé et le lecteur curieux sont perdus tous les deux |
| Éditer une page générée depuis le code | écrasée à la prochaine génération |
| Titre portant le nom technique | introuvable par recherche, le lecteur ne le tape pas |
| Sommaire alphabétique ou dans l'ordre du code | le spécialisé passe avant le générique |
| Groupe rassemblant des natures différentes sous un mot vague | égare avant même l'ouverture d'une page |
| Rassembler les bases de l'hôte dans un dossier à part | oblige à l'aller-retour sur un même sujet |
| Référence par objet quand un élément traverse plusieurs objets | autant de copies que d'apparitions, qui divergent |
| Aucune explication pour ce qui met deux dépendances en jeu | le lecteur assemble à l'aveugle, chaque how-to ayant l'air complet |
| Reformuler un titre sans router l'ancienne ancre | des liens déjà envoyés cessent de fonctionner |
| Créer un dossier pour une seule page | un niveau de profondeur que le lecteur doit deviner |
| Affirmer un comportement non vérifié | une documentation fausse coûte plus cher qu'une absente |
| Recopier une explication d'une autre page | les deux copies divergent au premier changement |
| Limites et cas particuliers en tête de page | bloquent le lecteur qui n'y était pas exposé |
| Liste à puces sur un contenu qui a des liens logiques | les connecteurs disparaissent, le raisonnement devient un inventaire |
| Paragraphes d'une phrase enchaînés | le texte est haché, la hiérarchie des idées se perd |
| Supposer une convention de projet au lieu de la lire | le corpus se retrouve avec deux organisations concurrentes |
| Citer un texte produit ailleurs sans en garder l'empreinte | la page devient fausse sans que rien ne le signale |
| Lire la source à l'affichage plutôt qu'en amont | la doc dépend du système documenté et varie d'un lecteur à l'autre |
| Réclamer un identifiant dédié au produit pour la doc | le produit se met à dépendre de sa documentation |
