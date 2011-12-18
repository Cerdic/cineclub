<?php

function forum_recuperer_titre($objet, $id_objet, $id_forum=0, $publie = false) {
	return forum_recuperer_titre_dist($objet,$id_objet,$id_forum,$publie);
}


function autoriser_ajoutertags($faire, $type, $id, $qui, $opt){
	return $qui['statut']=='0minirezo';
}
function autoriser_supprimertags($faire, $type, $id, $qui, $opt){
	return $qui['statut']=='0minirezo';
}
