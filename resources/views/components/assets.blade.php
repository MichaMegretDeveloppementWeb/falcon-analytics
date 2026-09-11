{{--
    Ce dont le paquet a besoin sur une page, declare en une ligne.

    La page et la racine l'appellent · une page qui ouvrira un bloc apres un
    clic peut aussi l'appeler elle-meme pour precharger, et la declaration est
    dedupliquee.

    **Le paquet nomme son nom et ses fichiers, rien d'autre** · ni nom de pile,
    ni cle de configuration, ni identifiant de deduplication. Le kit les tient,
    et il ecrit dans deux endroits · la pile reservee que rendent
    `@falconStyles` et `@falconScripts`, et l'etat de la requete, parce que le
    cadre vide ses piles des que la vue de plus haut niveau a fini.

    Un seul fichier ici · le paquet n'a aucun script d'administration. Ses
    graphiques sont de l'Alpine ecrit dans les vues, et Chart.js vient du kit.
--}}
<x-ui::assets package="analytics" :files="['analytics.css']" />
