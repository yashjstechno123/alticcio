<?php

$menu->current('main/content/assets');

$config->core_include("produit/asset", "outils/langue", "outils/phrase", "outils/form");
$config->core_include("outils/filter", "outils/pager");

$page->javascript[] = $config->core_media("jquery.min.js");
$page->javascript[] = $config->core_media("jquery.tablednd.js");
$page->javascript[] = $config->media("produit.js");

$page->jsvars[] = array(
	"edit_url" => $url->make("current", array('action' => 'edit', 'id' => "")),	
);

$page->css[] = $config->media("produit.css");

$titre_page = $dico->t("Assets");

$langue = new Langue($sql);
$id_langues = $langue->id($config->get("langue"));

$phrase = new Phrase($sql);

$asset = new Asset($sql, $phrase, $id_langues);

$pager_assets = new Pager($sql, array(20, 30, 50, 100, 200));
$filter_assets = new Filter($pager_assets, array(
	'id' => array(
		'title' => 'ID',
		'type' => 'between',
		'order' => 'DESC',
		'field' => 'a.id',
	),
	'titre' => array(
		'title' => $dico->t('Titre'),
		'type' => 'contain',
		'field' => 'a.titre',
	),
	'fichier' => array(
		'title' => $dico->t('Fichier'),
		'type' => 'contain',
		'field' => 'a.fichier',
	),
	'id_types_assets' => array(
		'title' => $dico->t('Type'),
		'type' => 'select',
		'field' => 'a.id_types_assets',
		'options' => $asset->codes_types(),
	),
), array(), "filter_assets");

if ($id = $url->get('id')) {
	$asset->load($id);
}

$action = $url->get('action');
if ($id = $url->get('id')) {
	$asset->load($id);
}

$form = new Form(array(
	'id' => "form-edit-asset-$id",
	'class' => "form-edit",
	'actions' => array(
		"save",
		"delete",
		"cancel",
	),
	'files' => array("asset_file"),
));

if (!isset($section)) {
	$section = "presentation";
}
if ($form->value('section')) {
	$section = $form->value('section');
}

$traduction = $form->value("lang");

$messages = array();

if ($form->is_submitted() and $form->validate()) {
	$data = $form->escape_values();
	switch ($form->action()) {
		case "translate":
		case "filter":
		case "pager":
		case "reaload":
			break;
		case "reset":
			$form->reset();
			$traduction = null;
			break;
		case "delete":
			$asset->delete($data);
			$form->reset();
			$url->redirect("current", array('action' => "", 'id' => ""));
			break;
		default :
			if ($action == "edit" or $action == "create") {
				if ($action == "edit") {
					foreach (array("gamme", "produit", "sku") as $link_type) {
						$filter_name = "filter_assets_".$link_type;
						$page->inc("snippets/assets-links", array('link_type' => $link_type));
						$$filter_name->clean_data($data['asset_links'], $link_type);
					}
				}
				$data['file'] = $form->value('asset_file');
				$data['path'] =  $config->get("asset_path");
				if ($id = $asset->save($data)) {
					$asset_saved = true; // used for hook
					$form->reset();
					if ($action != "edit") {
						$url->redirect("current", array('action' => "edit", 'id' => $id));
					}
					$asset->load($id);
				}
			}
			break;
	}
}

if ($form->changed()) {
	$messages[] = '<p class="message">'.$dico->t('AttentionNonSauvergarde').'</p>';
}
else if ($action == 'edit') {
	$asset_links = $asset->links();
}

if ($action == 'edit') {
	$form->default_values['asset'] = $asset->values;
	$form->default_values['phrases'] = $phrase->get($asset->phrases());
	$form->default_values['asset_links'] = $asset->links();
	/*
	$form->default_values['asset_gamme'] = $asset_links['gamme'];
	$form->default_values['asset_produit'] = $asset_links['produit'];
	$form->default_values['asset_sku'] = $asset_links['sku'];
	*/
}
else {
	$page->javascript[] = $config->core_media("filter-edit.js");
}

$form_start = $form->form_start();

$form->template = $page->inc("snippets/produits-form-template");

$template_inline = <<<HTML
#{label} : #{field} #{description}
HTML;

// variable $displayed_lang définie dans ce snippet
$main = $page->inc("snippets/translate");

$main .= $page->inc("snippets/messages");

$hidden = array('presentation' => "");

if ($action == "create" or $action == "edit") {
	$buttons['back'] = $page->l($dico->t('Retour'), $url->make("current", array('action' => "", 'id' => "")));
}

$buttons['new'] = $page->l($dico->t('NouvelAsset'), $url->make("current", array('action' => "create", 'id' => "")));

if ($action == "create" or $action == "edit") {
	$buttons['save'] = $form->input(array('type' => "submit", 'name' => "save", 'value' => $dico->t('Enregistrer') ));
}

$apercu = "";
$codes_types = $asset->codes_types();
if ($action == "edit") {
	$sections = array(
		'presentation' => $dico->t('Presentation'),
		'gammes' => $dico->t('Gammes'),
		'produits' => $dico->t('Produits'),
		'sku' => $dico->t('SKU'),
		//'tags' => $dico->t('Tags'),
	);
	// variable $hidden mise à jour dans ce snippet
	$left = $page->inc("snippets/produits-sections");

	$src = $config->get("asset_url").$asset->id;
	if ($codes_types[$asset->values['id_types_assets']] == "image" and $asset->values['actif'] and $asset->values['public']) {
		$apercu = <<<HTML
<a href="{$src}">
<img alt="{$asset->values['titre']}" title="{$dico->t("Telecharger")}" class="asset" src="{$src}" />
</a>
HTML;
	}
	else if($asset->values['actif']) {
		$apercu = <<<HTML
<ul class="buttons_actions"><li>
<a href="{$src}">
{$dico->t("Telecharger")}
</a>
</li></ul>
HTML;
	}
}
if ($action == "create" or $action == "edit") {
	$main .= <<<HTML
{$form->fieldset_start(array('legend' => $dico->t('Presentation'), 'class' => "produit-section produit-section-presentation".$hidden['presentation'], 'id' => "produit-section-presentation"))}
{$apercu}
{$form->input(array('type' => "file", 'name' => "asset_file", 'label' => $dico->t('Fichier')))}
{$form->input(array('name' => "asset[titre]", 'label' => $dico->t('Titre')))}
{$form->input(array('name' => "asset[phrase_nom]", 'type' => "hidden"))}
{$form->input(array('name' => "phrases[phrase_nom]", 'label' => $dico->t('Nom'), 'items' => $displayed_lang))}
{$form->input(array('name' => "asset[phrase_description]", 'type' => "hidden"))}
{$form->textarea(array('name' => "phrases[phrase_description]", 'label' => $dico->t('Description'), 'items' => $displayed_lang, 'class' => "dteditor"))}
{$form->select(array('name' => "asset[id_types_assets]", 'label' => $dico->t('TypeAsset'), 'options' => $codes_types))}
{$form->input(array('type' => "checkbox", 'name' => "asset[actif]", 'label' => $dico->t('Actif')))}
{$form->input(array('type' => "checkbox", 'name' => "asset[public]", 'label' => $dico->t('Public')))}
{$form->input(array('name' => "asset[copyright]", 'label' => $dico->t('Copyright')))}
{$form->fieldset_end()}
HTML;
}
if ($action == "edit") {
	$main .= <<<HTML
{$form->fieldset_start(array('legend' => $dico->t('Gammes'), 'class' => "produit-section produit-section-gammes".$hidden['gammes'], 'id' => "produit-section-gammes"))}
{$page->inc("snippets/assets-links", array('link_type' => "gamme"))}
{$form->fieldset_end()}
{$form->fieldset_start(array('legend' => $dico->t('Produits'), 'class' => "produit-section produit-section-produits".$hidden['produits'], 'id' => "produit-section-produits"))}
{$page->inc("snippets/assets-links", array('link_type' => "produit"))}
{$form->fieldset_end()}
{$form->fieldset_start(array('legend' => $dico->t('SKU'), 'class' => "produit-section produit-section-sku".$hidden['sku'], 'id' => "produit-section-sku"))}
{$page->inc("snippets/assets-links", array('link_type' => "sku"))}
{$form->fieldset_end()}

{$form->input(array('type' => "hidden", 'name' => "asset[id]"))}
{$form->input(array('type' => "hidden", 'name' => "section", 'value' => $section))}
HTML;
	$buttons['delete'] = $form->input(array('type' => "submit", 'name' => "delete", 'class' => "delete", 'value' => $dico->t('Supprimer')));

	foreach (array("gamme", "produit", "sku") as $link_type) {
		$filter_name = "filter_assets_{$link_type}";
		foreach ($$filter_name->selected() as $selected_attribut) {
			$main .= $form->hidden(array('name' => "asset_{$link_type}[$selected_attribut][classement]", 'if_not_yet_rendered' => true));
			$main .= $form->hidden(array('name' => "asset_{$link_type}[$selected_attribut][groupe]", 'if_not_yet_rendered' => true));
		}
	}
}

switch($action) {
	case "create" :
		$titre_page = $dico->t('CreerNouvelAsset');
		break;
	case "edit" :
		$titre_page = $dico->t('EditerAsset')." # ID : ".$id;
		break;
	default :
		$titre_page = $dico->t('ListeOfAssets');
		$filter = $filter_assets;
		$pager = $pager_assets;
		$asset->liste($id_langues, $filter);
		$main = $page->inc("snippets/filter");
		break;
}

$form_end = $form->form_end();