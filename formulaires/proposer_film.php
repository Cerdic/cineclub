<?php

if (!defined('_ECRIRE_INC_VERSION')) return;

include_spip('inc/editer');
/**
 * Chargement des valeurs
 * @return array
 */
function formulaires_proposer_film_charger_dist($id_rubrique,$id_article=0){
	$valeurs = formulaires_editer_objet_charger('article',$id_article,$id_rubrique,0,'','');
	// il faut enlever l'id_rubrique car la saisie se fait sur id_parent
	// et id_rubrique peut etre passe dans l'url comme rubrique parent initiale
	// et sera perdue si elle est supposee saisie
	$valeurs['_id_rubrique'] = $id_rubrique;
	return $valeurs;
}

function formulaires_proposer_film_verifier_dist($id_rubrique,$id_article=0){
	$erreurs = formulaires_editer_objet_verifier('article',$id_article,array('titre'));
	if (!isset($erreurs['titre'])
		AND (
			_request('search')
		  OR !$imdb=_request('imdb_data')
	    OR !$imdb = unserialize(base64_decode($imdb))
		)){
		$titre = _request('titre');

		if (preg_match(',http://(?:www.)?imdb.(?:fr|com)/title/([^/]+)/,',$titre,$m))
			$api = parametre_url("http://www.imdbapi.com/",'i',$m[1]);
		else
			$api = parametre_url("http://www.imdbapi.com/",'t',$titre);
		include_spip('inc/distant');
		$json = recuperer_page($api);
		$json = json_decode($json,true);
		if (!$json){
			$erreurs['titre'] = _T('proposer_film:erreur_titre_inconnu_imbd');
		}
		elseif(!isset($json['Title'])){
			$erreurs['titre'] = _T('proposer_film:erreur_imbd');
		}
		else {
			if ($json['Poster']=="N/A")
				$json['Poster']="";
			else {
				$adresse = $GLOBALS['meta']["adresse_site"];
				$GLOBALS['meta']["adresse_site"] = '';
				include_spip('inc/distant');
				$json['Poster'] = _DIR_RACINE . copie_locale($json['Poster']);
				$GLOBALS['meta']["adresse_site"] = $adresse;
			}
			$erreurs['_imdb'] = $json;
		}
	}
	return $erreurs;

}

// http://doc.spip.org/@inc_editer_article_dist
function formulaires_proposer_film_traiter_dist($id_rubrique,$id_article=0){
	$imdb = _request('imdb_data');
	$imdb = unserialize(base64_decode($imdb));

	set_request('id_parent',$id_rubrique);
	set_request('titre',$imdb['Title']);

	$desc = recuperer_fond('modeles/imdb',array('imdb'=>$imdb));

	set_request('descriptif',"<html>$desc</html>");
	include_spip('inc/autoriser');
	// lever l'exception pour le statut
	$res = formulaires_editer_objet_traiter('article',$id_article,$id_rubrique);
	if ($res['id_article']){
		autoriser_exception('modifier','article',$res['id_article']);
		include_spip("action/editer_article");
		article_modifier($res['id_article'],array('statut'=>'prop'));
		autoriser_exception('modifier','article',$res['id_article'],false);
		if (!intval($id_article))
			$res['message_ok'] = _T('proposer_film:message_ok_propose');
		$res['message_ok'].='<script type="text/javascript">jQuery(".listefilms").ajaxReload({args:{debut_film:"@'.$res['id_article'].'"}});</script>';
		set_request('titre','');
	}
	$res['editable'] = true;

	return $res;
}


?>
