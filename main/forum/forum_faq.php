<?php
/*
** +---------------------------------------------------+
** | Name :		~/main/forum/forum_faq.php
** | Begin :	15/09/2005
** | Last :		21/08/2007
** | User :		Genova
** | Project :	Fire-Soft-Board 2 - Copyright FSB group
** | License :	GPL v2.0
** +---------------------------------------------------+
*/

/*
** Affiche la FAQ du forum
*/
class Fsb_frame_child extends Fsb_frame
{
	// Paramètres d'affichage de la page (barre de navigation, boite de stats)
	public $_show_page_header_nav = TRUE;
	public $_show_page_footer_nav = TRUE;
	public $_show_page_stats = FALSE;

	// Arguments de la page
	public $keyword = '';
	public $area = '';
	public $section = '';
	
	// Contient les sections trouvées lors d'une recherche
	public $result = array();
	
	// Données de la FAQ
	public $faq_data = array();
	
	/*
	** Constructeur
	*/
	public function main()
	{		
		// Chargement des clefs pour la FAQ
		$sql = 'SELECT lang_key, lang_value
				FROM ' . SQL_PREFIX . 'langs
				WHERE lang_name = \'' . Fsb::$session->data['u_language'] . '\'
					AND lang_key LIKE \'_fsb_faq_%\'';
		$result = Fsb::$db->query($sql, 'langs_');
		while ($row = Fsb::$db->row($result))
		{
			if (preg_match('#^_fsb_faq_\[([a-zA-Z0-9_]+?)\]\[([a-zA-Z0-9_]+?)\]\[(question|answer)\]$#', $row['lang_key'], $match))
			{
				$GLOBALS['faq_data'][$match[1]][$match[2]][$match[3]] = $row['lang_value'];
			}
		}
		Fsb::$db->free($result);
		
		$this->faq_data = $GLOBALS['faq_data'];
		unset($GLOBALS['faq_data']);
		
		// Récupération des arguments de la page
		$this->area =		Http::request('area');
		$this->section =	Http::request('section');
		$this->keyword =	trim(Http::request('keyword'));
		
		if (!isset($this->faq_data[$this->section]))
		{
			$this->section = 'forum';
		}
		
		// Recherche de mots ?
		if (!empty($this->keyword))
		{
			$this->search_keyword();
		}

		// Affichage des sections
		$list_section = array(
			'forum' =>		TRUE,
			'fsbcode' =>	TRUE,
			'modo' =>		(Fsb::$session->auth() >= MODO) ? TRUE : FALSE,
			'admin' =>		(Fsb::$session->auth() >= MODOSUP) ? TRUE : FALSE,
			'info' =>		TRUE,
		);
		
		if (!$list_section[$this->section])
		{
			Display::message('faq_not_allowed');
		}
		
		foreach ($list_section AS $key => $value)
		{
			if ($value)
			{
				Fsb::$tpl->set_switch('show_menu_panel');
				Fsb::$tpl->set_blocks('module', array(
					'IS_SELECT' =>	($this->section == $key) ? TRUE : FALSE,
					'URL' =>		sid(ROOT . 'index.' . PHPEXT . '?p=faq&amp;section=' . $key . '&amp;keyword=' . htmlspecialchars($this->keyword)),
					'NAME' =>		Fsb::$session->lang('faq_section_' . $key),
				));
			}
		}

		// Déclaration du template
		Fsb::$tpl->set_file('forum/forum_faq.html');
		Fsb::$tpl->set_vars(array(
			'KEYWORD' =>			htmlspecialchars($this->keyword),
			'FAQ_SECTION' =>		Fsb::$session->lang('faq_section_' . $this->section),
			'MENU_HEADER_TITLE' =>	Fsb::$session->lang('faq_title'),

			'U_ACTION' =>			sid(ROOT . 'index.' . PHPEXT . '?p=faq&amp;section=' . $this->section),
		));

		if (!empty($this->area))
		{
			$this->show_answer();
		}
		$this->list_faq();
	}
	
	/*
	** Affiche la liste des sujets pour la FAQ, en fonction de la recherche et de la section
	*/
	public function list_faq()
	{		
		$result_exists = FALSE;
		foreach ($this->faq_data[$this->section] AS $current_area => $data)
		{
			if (!count($this->result) || in_array($current_area, $this->result))
			{
				$result_exists = TRUE;
				Fsb::$tpl->set_blocks('area', array(
					'NAME' =>		$data['question'],
					'URL' =>		sid(ROOT . 'index.' . PHPEXT . '?p=faq&amp;area=' . $current_area . '&amp;section=' . $this->section . '&amp;keyword=' . htmlspecialchars($this->keyword)),
				));
			}
		}
		
		if (!$result_exists)
		{
			Fsb::$tpl->set_switch('no_result');
		}
	}
	
	/*
	** Affiche la réponse
	*/
	public function show_answer()
	{
		$fsbcode = new Parser_fsbcode();

		// On vérifie l'existance de la section
		if (!isset($this->faq_data[$this->section][$this->area]))
		{
			Display::message('faq_area_no_exists');
		}

		Fsb::$tpl->set_switch('show_answer');
		
		Fsb::$tpl->set_vars(array(
			'FAQ_QUESTION' =>	$this->faq_data[$this->section][$this->area]['question'],
			'FAQ_ANSWER' =>		$fsbcode->parse($this->faq_data[$this->section][$this->area]['answer']),
		));
	}
	
	/*
	** Recherche dans la FAQ les mots clefs cherchés
	*/
	public function search_keyword()
	{
		$this->result = array();
		$words = explode(' ', $this->keyword);
		foreach ($this->faq_data[$this->section] AS $current_area => $data)
		{
			foreach ($words AS $word)
			{
				if (String::is_matching("*$word*", $data['question']) || String::is_matching("*$word*", $data['answer']))
				{
					$this->result[] = $current_area;
				}
			}
		}
		
		if (!count($this->result))
		{
			$this->result[] = 'no_result';
		}
	}
}

/* EOF */