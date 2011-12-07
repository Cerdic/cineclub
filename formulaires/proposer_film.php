<?php

if (!defined('_ECRIRE_INC_VERSION')) return;

include_spip('inc/editer');
/**
 * Chargement des valeurs
 * @return array
 */
function formulaires_proposer_film_charger_dist($id_rubrique,$id_article=0){
	if (!$GLOBALS['visiteur_session']['id_auteur'])
		return
			"<p class='center'>"
			. _T("proposer_film:info_identifiez_vous")
		  . ' <a href="'.generer_url_public('login',"url=".self()).'">'._T('public:lien_connecter')."</a>"
	    . "</p>";

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

		$json = renseigner_imdb($titre);
		if (is_string($json))
			$erreurs['titre'] = $json;
		else
			$erreurs['_imdb'] = $json;
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


function renseigner_imdb($search){
	$id = "";
	$html = "";
	$api_endpoint = "http://www.imdbapi.com/";
	if (preg_match(',http://(?:www.)?imdb.(?:fr|com)/title/([^/]+)/,',$search,$m))
		$id = $m[1];
	elseif (preg_match(',^tt\d+$,i',$search))
		$id = $search;

	if ($id)
		$url = parametre_url($api_endpoint,'i',$id);
	else
		$url = parametre_url($api_endpoint,'t',$search);

	include_spip('inc/distant');
	$json = recuperer_page($url);
	$json = json_decode($json,true);
	if ($json AND $id){
		$url = "http://www.imdb.com/title/$id/";
		$html = recuperer_page($url);
		$json = extraire_imdb_from_html($id, $html);
	}
	if (!$json)
		return _T('proposer_film:erreur_titre_inconnu_imbd');

	if(!isset($json['Title']))
		return _T('proposer_film:erreur_imbd');

	$id = $json['ID'];

	// corriger le poster
	if ($json['Poster']=="N/A")
		$json['Poster']="";
	else {
		$adresse = $GLOBALS['meta']["adresse_site"];
		$GLOBALS['meta']["adresse_site"] = '';
		$json['Poster'] = _DIR_RACINE . copie_locale($json['Poster']);
		$GLOBALS['meta']["adresse_site"] = $adresse;
	}

	// corriger le titre
	if (!$html){
		$url = "http://www.imdb.com/title/$id/";
		$html = recuperer_page($url);
		$json2 = extraire_imdb_from_html($id, $html);
		$json['Title'] = $json2['Title'];
	}

	return $json;
}


/*
 http://www.imdb.com/title/tt0276929/
 */
function extraire_imdb_from_html($id, $html){
	$json = array(
		'Title' => '',
		"Year" => "",
		"Rated" => "",
		"Released" => "",
		"Genre" => "",
		"Director" => "",
		"Writer" => "",
		"Actors" => "",
		"Plot" => "",
		"Poster" => "",
		"Runtime" => "",
		"Rating" => "",
		"Votes" => "",
		"ID" => "$id",
		"Response" => "True",
	);

	$h1 = extraire_balise($html,"h1");
	$a = extraire_balise($h1,"a");
	$json['Year'] = strip_tags($a);

	$h1 = preg_replace(",<span.*</span>,Uims","",$h1);
	$h1 = trim(strip_tags($h1));
	$json['Title'] = $h1;

	if (preg_match(",http://ia.media-imdb.com/images/[^'\"]*.jpg,i",$html,$m))
		$json['Poster'] = $m[0];

	if (preg_match(",<a[^>]*itemprop=['\"]director['\"].*</a>,Uims",$html,$m))
		$json['Director'] = strip_tags($m[0]);

	$json = array_map('trim',$json);
	include_spip('inc/charset');
	$json = array_map('html2unicode',$json);
	$json = array_map('unicode2utf8',$json);

	return $json;
}

function unicode2utf8($texte) {

	// 1. Entites &#128; et suivantes
	$vu = array();
	if (preg_match_all(',&#0*([1-9][0-9]+);,S',
	$texte, $regs, PREG_SET_ORDER))
	foreach ($regs as $reg) {
		if ($reg[1]>127 AND !isset($vu[$reg[0]]))
			$vu[$reg[0]] = caractere_utf_8($reg[1]);
	}
	//$texte = str_replace(array_keys($vu), array_values($vu), $texte);

	// 2. Entites > &#xFF;
	//$vu = array();
	if (preg_match_all(',&#x0*([1-9a-f][0-9a-f]+);,iS',
	$texte, $regs, PREG_SET_ORDER))
	foreach ($regs as $reg) {
		if (!isset($vu[$reg[0]]))
			$vu[$reg[0]] = caractere_utf_8(hexdec($reg[1]));
	}
	return str_replace(array_keys($vu), array_values($vu), $texte);

}
?>
