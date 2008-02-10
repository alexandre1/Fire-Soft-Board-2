<?php
/*
** +---------------------------------------------------+
** | Name :			~/main/forum/forum_forum.php
** | Begin :		12/05/2005
** | Last :			19/12/2007
** | User :			Genova
** | Project :		Fire-Soft-Board 2 - Copyright FSB group
** | License :		GPL v2.0
** +---------------------------------------------------+
*/

/*
** Affiche la liste des sujets d'un forum, ainsi que les sous forums
*/
class Fsb_frame_child extends Fsb_frame
{
	// Paramètres d'affichage de la page (barre de navigation, boite de stats)
	public $_show_page_header_nav = TRUE;
	public $_show_page_footer_nav = TRUE;
	public $_show_page_stats = FALSE;

	// Navigation
	public $nav = array();

	// <title>
	public $tag_title = '';

	// Modération du forum ?
	public $moderation = FALSE;

	// ID du forum
	public $id;
	
	// Données sur le forum
	public $forum_data;

	// Page actuelle
	public $page;

	// Ordre du sujet
	public $check_order = array('t_last_p_time', 't_title', 't_total_view', 't_total_post', 'f_u_nickname');
	public $order = 't_last_p_time';
	public $dir = 'desc';
	
	/*
	** Constructeur
	*/
	public function main()
	{
		$this->id = Http::request('f_id');
		if ($this->id{0} == 'f')
		{
			$this->id = substr($this->id, 1);
		}
		$this->id = intval($this->id);

		$this->page = intval(Http::request('page'));
		if (!$this->page)
		{
			$this->page = 1;
		}

		$this->order = trim(Http::request('order'));
		if (!in_array($this->order, $this->check_order))
		{
			$this->order = 't_last_p_time';
		}

		$this->dir = trim(Http::request('dir'));
		if ($this->dir != 'asc' && $this->dir != 'desc')
		{
			$this->dir = 'desc';
		}

		// Données du forum
		if (!$this->get_forum_data())
		{
			return ;
		}

		// Qui a posté ?
		if (Http::request('who_posted'))
		{
			$this->who_posted();
			return ;
		}

		// Modération
		if ((Http::request('moderation_delete', 'post') || Http::request('moderation_move', 'post') || Http::request('moderation_lock', 'post') || Http::request('moderation_unlock', 'post'))
			&& Fsb::$session->is_authorized($this->id, 'ga_moderator') && Fsb::$session->is_authorized('mass_moderation'))
		{
			$this->mass_moderation();
		}

		// Marquer les sujets du forums comme lus ?
		if (Http::request('markread'))
		{
			$this->forum_markread();
		}

		$this->show_subforums();
		$this->show_topics();
	}

	/*
	** Met tous les sujets de ce forum en lu
	*/
	public function forum_markread()
	{
		$markread_subforum = (Http::request('subforum')) ? TRUE : FALSE;
		$idx = array();
		if ($markread_subforum)
		{
			$sql = 'SELECT f_id
					FROM ' . SQL_PREFIX . 'forums
					WHERE f_left > ' . $this->forum_data['f_left'] . '
						AND f_right < ' . $this->forum_data['f_right'];
			$result = Fsb::$db->query($sql);
			while ($row = Fsb::$db->row($result))
			{
				$idx[] = $row['f_id'];
			}
			Fsb::$db->free($result);
		}
		else
		{
			$idx[] = $this->id;
		}

		Forum::markread('forum', $idx);
		Http::redirect('index.' . PHPEXT . '?p=forum&f_id=' . $this->id);
	}

	/*
	** Données du forum, vérification du type, du mot de passe, etc ...
	*/
	public function get_forum_data()
	{
		// On récupère les données du forum
		$sql = 'SELECT *
				FROM ' . SQL_PREFIX . 'forums
				WHERE f_id = ' . $this->id;
		$result = Fsb::$db->query($sql, 'forums_' . $this->id . '_');
		$this->forum_data = Fsb::$db->row($result);
		Fsb::$db->free($result);

		// Si c'est une catégorie on redirige vers l'index
		if (!$this->forum_data['f_level'])
		{
			Http::redirect(ROOT . 'index.' . PHPEXT . '?p=index&cat=' . $this->id);
		}

		// Droit d'accéder au forum ?
		if (!Fsb::$session->is_authorized($this->id, 'ga_view') || !Fsb::$session->is_authorized($this->id, 'ga_view_topics'))
		{
			if (!Fsb::$session->is_logged())
			{
				Http::redirect(ROOT . 'index.' . PHPEXT . '?p=login&redirect=forum&f_id=' . $this->id);
			}
			else
			{
				Display::message('not_allowed');
			}
		}

		// Navigation de la page
		$this->nav = Forum::nav($this->id, array(), $this);

		// Forum avec mot de passe ?
		if ($this->forum_data['f_password'] && !Display::forum_password($this->id, $this->forum_data['f_password'], ROOT . 'index.' . PHPEXT . '?p=forum&amp;f_id=' . $this->id))
		{
			// L'accès est refusé, on affiche le formulaire du mot de passe et on doit donc quitter la classe
			return (FALSE);
		}

		// Modération du forum ?
		if (Http::request('moderation') && Fsb::$session->is_authorized($this->id, 'ga_moderator'))
		{
			$this->moderation = TRUE;
		}

		// Si ce n'est pas une sous catégorie, on affiche les sujets
		switch ($this->forum_data['f_type'])
		{
			case FORUM_TYPE_NORMAL :
				Fsb::$tpl->set_switch('show_topics');
			break;

			case FORUM_TYPE_SUBCAT :
				Fsb::$tpl->unset_switch('show_topics');
			break;

			case FORUM_TYPE_INDIRECT_URL :
				// Redirection vers le bon lien après incrémentation du nombre de vu
				Fsb::$db->update('forums', array(
					'f_location_view' =>	array('(f_location_view + 1)', 'is_field' => TRUE),
				), 'WHERE f_id = ' . $this->id);
				Http::redirect($this->forum_data['f_location']);
			break;

			case FORUM_TYPE_DIRECT_URL :
				// En théorie le membre n'est pas censé passer par là, mais si jamais c'est le cas on le redirige vers le site en question
				Http::redirect($this->forum_data['f_location']);
			break;
		}

		// Thème pour le forum ?
		if ($this->forum_data['f_tpl'])
		{
			$set_tpl = ROOT . 'tpl/' . $this->forum_data['f_tpl'];
			Fsb::$session->data['u_tpl'] = $this->forum_data['f_tpl'];
			Fsb::$tpl->set_template($set_tpl . '/files/', $set_tpl . '/cache/');
		}

		return (TRUE);
	}

	/*
	** Affiche les sous forums
	*/
	public function show_subforums()
	{
		// On affiche les sous forums s'il y en a
		if ($this->forum_data['f_right'] - $this->forum_data['f_left'] > 1)
		{
			$check_level = (Fsb::$cfg->get('display_subforums')) ? '' : ' AND f.f_level BETWEEN ' . ($this->forum_data['f_level'] - 1) . ' AND ' . ($this->forum_data['f_level'] + 2);
			$result = Forum::query('WHERE f.f_left >= ' . $this->forum_data['f_left'] . ' AND f.f_right <= ' . $this->forum_data['f_right'] . $check_level);
			while ($forum = Fsb::$db->row($result))
			{
				if ($forum['f_id'] == $this->id)
				{
					$show_forum_data = TRUE;
					$forum_topic_read = array();
					if (Fsb::$session->is_logged())
					{
						$forum_topic_read = Forum::get_topics_read('WHERE f.f_left >= ' . $this->forum_data['f_left'] . ' AND f.f_right <= ' . $this->forum_data['f_right']);
					}
				}
				else if (Fsb::$session->is_authorized($forum['f_id'], 'ga_view'))
				{
					if (Fsb::$cfg->get('display_subforums') || $forum['f_parent'] == $this->id)
					{
						if ($show_forum_data)
						{
							Fsb::$tpl->set_blocks('cat', array(
								'NAME' =>		$this->forum_data['f_name'],
							));
							$show_forum_data = FALSE;
						}

						// Forum lu ou non lu ?
						$is_read = (Fsb::$session->is_logged() && isset($forum_topic_read[$forum['f_id']]) && $forum_topic_read[$forum['f_id']] > 0) ? FALSE : TRUE;

						// On affiche le forum
						Forum::display($forum, 'forum', $this->forum_data['f_level'], $is_read);
					}
					else if (!Fsb::$cfg->get('display_subforums') && Fsb::$session->is_authorized($forum['f_parent'], 'ga_view'))
					{
						// Forum lu ou non lu ?
						$is_read = (Fsb::$session->is_logged() && isset($forum_topic_read[$forum['f_id']]) && $forum_topic_read[$forum['f_id']] > 0) ? FALSE : TRUE;

						// On affiche le sous forum
						Forum::display($forum, 'subforum', $this->forum_data['f_level'], $is_read);
					}
				}
			}
		}
	}
	
	/*
	** Affiche la liste des sujets
	*/
	public function show_topics()
	{
		// Variable pour garder en mémoire les types de sujets déjà affichés
		$cache_topic_type = array();

		// Total de sujets dans ce forum
		$sql = 'SELECT COUNT(*) AS total
				FROM ' . SQL_PREFIX . 'topics
				WHERE (f_id = ' . $this->id . ' OR t_trace = ' . $this->id . ')
					AND t_approve = 0';
		$total = Fsb::$db->get($sql, 'total');

		// Le membre peut modérer ce forum ?
		if (Fsb::$session->is_authorized($this->id, 'ga_moderator') && Fsb::$session->is_authorized('mass_moderation'))
		{
			Fsb::$tpl->set_switch('can_moderate_forum');

			// Le forum est en cours de modération ?
			if ($this->moderation)
			{
				Fsb::$tpl->set_switch('moderation');
			}
		}

		// Utilisation de la pagination ?
		if (ceil($total / Fsb::$cfg->get('topic_per_page')) > 1)
		{
			Fsb::$tpl->set_switch('forum_pagination');
		}

		// On regarde si le membre peut créer des messages
		$can_create_post = FALSE;
		foreach ($GLOBALS['_topic_type'] AS $value)
		{
			if (Fsb::$session->is_authorized($this->id, 'ga_create_' . $value))
			{
				$can_create_post = TRUE;
				break;
			}
		}

		// Règles du forums ?
		if (!empty($this->forum_data['f_rules']))
		{
			Fsb::$tpl->set_switch('forum_rules');
		}

		// Interdiction de poster ?
		if (Fsb::$session->data['u_warn_post'] == 1 || Fsb::$session->data['u_warn_post'] >= CURRENT_TIME)
		{
			Fsb::$tpl->set_vars(array(
				'WARN_INFO' =>	(Fsb::$session->data['u_warn_post'] >= CURRENT_TIME) ? sprintf(Fsb::$session->lang('not_allowed_to_post_until'), Fsb::$session->print_date(Fsb::$session->data['u_warn_post'])) : Fsb::$session->lang('not_allowed_to_post'),
			));
		}

		// Forum vérouillé ?
		if ($this->forum_data['f_status'] == LOCK)
		{
			Fsb::$tpl->set_switch('forum_locked');
		}

		// Redirection vers la page de connexion ?
		$redirect_login = (!Fsb::$session->is_logged() && !$can_create_post) ? 'login&amp;redirect=' : '';

		// Affichage des annonces globales ?
		$sql_announce = '';
		if ($this->forum_data['f_global_announce'])
		{
			$total += Fsb::$cfg->get('total_global_announce');
			$sql_announce = ' OR t.t_type = ' . GLOBAL_ANNOUNCE;
		}

		// Requète récupérant les sujets de ce forum, paginé page par page
		$sql = 'SELECT t.*, uf.u_id AS f_u_id, uf.u_nickname AS f_u_nickname, uf.u_color AS f_u_color, u.u_nickname, u.u_color, tr.tr_last_time, tr.p_id AS last_unread_id
				FROM ' . SQL_PREFIX . 'topics t
				LEFT JOIN ' . SQL_PREFIX . 'users u
					ON u.u_id = t.t_last_u_id
				LEFT JOIN ' . SQL_PREFIX . 'users uf
					ON uf.u_id = t.u_id
				LEFT JOIN ' . SQL_PREFIX . 'topics_read tr
					ON t.t_id = tr.t_id AND tr.u_id = ' . Fsb::$session->id() . '
				WHERE (t.f_id = ' . $this->id . ' OR t.t_trace = ' . $this->id . $sql_announce . ' )
					AND t.t_approve = 0
				ORDER BY t.t_type, ' . $this->order . ' ' . $this->dir . '
				LIMIT ' . (($this->page - 1) * Fsb::$cfg->get('topic_per_page')) . ', ' . Fsb::$cfg->get('topic_per_page');
		$result = Fsb::$db->query($sql);
		while ($row = Fsb::$db->row($result))
		{
			// On affiche le header du type de topic ?
			if (!isset($cache_topic_type[$row['t_type']]))
			{
				$cache_topic_type[$row['t_type']] = TRUE;
				Fsb::$tpl->set_blocks('topic', array(
					'LANG' =>	Fsb::$session->lang('topic_type_' . $GLOBALS['_topic_type'][$row['t_type']] . 's'),
				));
			}

			Fsb::$tpl->set_switch('show_list_order');

			// Pagination du sujet
			$total_page = $row['t_total_post'] / Fsb::$cfg->get('post_per_page');
			$topic_pagination = ($total_page > 1) ? Html::pagination(0, $total_page, 'index.' . PHPEXT . '?p=topic&amp;t_id=' . $row['t_id'], NULL, TRUE) : FALSE;

			// Sujet lu ?
			list($is_read, $last_url) = check_read_post($row['t_last_p_id'], $row['t_last_p_time'], $row['t_id'], $row['tr_last_time'], $row['last_unread_id']);

			// Image du sujet
			if ($GLOBALS['_topic_type'][$row['t_type']] == 'post')
			{
				$topic_img = (($is_read) ? '' : 'new_') . 'post' . (($row['t_status'] == LOCK) ? '_locked' : '');
			}
			else
			{
				$topic_img = (($is_read) ? '' : 'new_') . 'announce';
			}

			Fsb::$tpl->set_blocks('topic.t', array(
				'ID' =>					$row['t_id'],
				'NAME' =>				htmlspecialchars(Parser::censor($row['t_title'])),
				'EXTRA_NAME' =>			(($row['t_trace'] == $this->id) ? '[' . Fsb::$session->lang('moved') . ']' : '') . (($row['t_poll'] == TOPIC_POLL) ? '[' . Fsb::$session->lang('poll') . ']' : ''),
				'FIRST_LOGIN' =>		Html::nickname($row['f_u_nickname'], $row['f_u_id'], $row['f_u_color']),
				'FIRST_TIME' =>			Fsb::$session->print_date($row['t_time']),
				'LAST_LOGIN' =>			Html::nickname($row['t_last_p_nickname'], $row['t_last_u_id'], $row['u_color']),
				'IS_READ' =>			$is_read,
				'LAST_DATE' =>			Fsb::$session->print_date($row['t_last_p_time']),
				'DESCRIPTION' =>		htmlspecialchars(String::truncate($row['t_description'], 50)),
				'VIEWS' =>				$row['t_total_view'],
				'ANSWERS' =>			$row['t_total_post'] - 1,
				'PAGINATION' =>			$topic_pagination,
				'IMG' =>				Fsb::$session->img($topic_img),
				'IMG_ALT' =>			Fsb::$session->lang('alt_' . $topic_img),

				'U_TOPIC' =>			sid(ROOT . 'index.' . PHPEXT . '?p=topic&amp;t_id=' . $row['t_id']),
				'U_LAST_POST' =>		sid(ROOT . 'index.' . PHPEXT . '?p=topic&amp;' . $last_url),
				'U_WHO_POSTED' =>		sid(ROOT . 'index.' . PHPEXT . '?p=forum&amp;f_id=' . $this->id . '&amp;who_posted=' . $row['t_id']),
			));
		}
		Fsb::$db->free($result);

		// Liste de classement des sujets
		$list_order = Html::create_list('order', $this->order, array(
			't_last_p_time' =>		Fsb::$session->lang('forum_order_time'),
			't_title' =>			Fsb::$session->lang('forum_order_title'),
			't_total_view' =>		Fsb::$session->lang('forum_order_view'),
			't_total_post' =>		Fsb::$session->lang('forum_order_post'),
			'f_u_nickname' =>			Fsb::$session->lang('forum_order_nickname'),
		));

		$list_direction = Html::create_list('dir', $this->dir, array(
			'asc' =>	Fsb::$session->lang('forum_order_asc'),
			'desc' =>	Fsb::$session->lang('forum_order_desc'),
		));

		// Si on change l'ordre par défaut, on ajoute les paramètres à la pagination
		$purl = ($this->moderation) ? '&amp;moderation=true' : '';
		if ($this->order != 't_last_p_time' || $this->dir != 'desc')
		{
			$purl = '&amp;order=' . $this->order . '&amp;dir=' . $this->dir;
		}

		$parser = new Parser();

		// Variables globales de template pour la page
		$total_page = ceil($total / Fsb::$cfg->get('topic_per_page'));
		Fsb::$tpl->set_file('forum/forum_forum.html');
		Fsb::$tpl->set_vars(array(
			'L_TOPIC_LIST' =>		sprintf(Fsb::$session->lang('topic_list'), $this->forum_data['f_name']),
			'PAGINATION' =>			Html::pagination($this->page, $total_page, ROOT . 'index.' . PHPEXT . '?p=forum&amp;f_id=' . $this->id . $purl),
			'CAN_POST_NEW' =>		(!$can_create_post || ($this->forum_data['f_status'] == LOCK && !Fsb::$session->is_authorized($this->id, 'ga_moderator'))) ? FALSE : TRUE,
			'FORUM_RULES' =>		$parser->message($this->forum_data['f_rules']),
			'LIST_ORDER_TOPIC' =>	$list_order,
			'LIST_DIR_TOPIC' =>		$list_direction,
			'QUICKSEARCH_LANG' =>	Fsb::$session->lang('forum_search_in'),
			
			'U_FORUM_MARKREAD' =>	sid(ROOT . 'index.' . PHPEXT . '?p=forum&amp;markread=true&amp;f_id=' . $this->id),
			'U_SUBFORUM_MARKREAD' =>sid(ROOT . 'index.' . PHPEXT . '?p=forum&amp;markread=true&amp;subforum=true&amp;f_id=' . $this->id),
			'U_TOPIC_NEW' =>		sid(ROOT . 'index.' . PHPEXT . '?p=' . $redirect_login . 'post&amp;mode=topic&amp;id=' . $this->id),
			'U_MODERATE_FORUM' =>	sid(ROOT . 'index.' . PHPEXT . '?p=forum&amp;f_id=' . $this->id . '&amp;page=' . $this->page . (($this->moderation) ? '' : '&amp;moderation=true')),
			'U_QUICKSEARCH' =>		sid(ROOT . 'index.' . PHPEXT . '?p=search&amp;in[]=post&amp;in%5B%5D=title&amp;print=topic&amp;forums[]=' . $this->id),
			'U_LOW_FORUM' =>		sid(ROOT . 'index.' . PHPEXT . '?p=low&amp;mode=forum&amp;id=' . $this->id),
		));

		// Balise META pour la syndications RSS
		Http::add_meta('link', array(
			'rel' =>		'alternate',
			'type' =>		'application/rss+xml',
			'title' =>		Fsb::$session->lang('rss'),
			'href' =>		sid(ROOT . 'index.' . PHPEXT . '?p=rss&amp;mode=forum&amp;id=' . $this->id),
		));

		// Relations
		Http::add_relation($this->page, $total_page, ROOT . 'index.' . PHPEXT . '?p=forum&amp;f_id=' . $this->id . $purl);
	}

	/*
	** Modération de masse du forum
	*/
	public function mass_moderation()
	{
		$action = (array) Http::request('action', 'post');
		if ($action)
		{
			$action = array_map('intval', $action);
			if (Http::request('moderation_delete', 'post'))
			{
				Moderation::delete_topics('t_id IN (' . implode(', ', $action) . ')');
				Display::message('forum_moderation_delete', ROOT . 'index.' . PHPEXT . '?p=forum&amp;f_id=' . $this->id . '&amp;moderation=true', 'forum_forum_moderation');
			}
			else if (Http::request('moderation_move', 'post'))
			{
				Http::redirect(ROOT . 'index.' . PHPEXT . '?p=modo&module=move&f_id=' . $this->id . '&topics=' . urlencode(implode(',', $action)));
			}
			else if (Http::request('moderation_lock', 'post'))
			{
				Moderation::lock_topic($action, LOCK, $this->id);
				Display::message('forum_moderation_lock', ROOT . 'index.' . PHPEXT . '?p=forum&amp;f_id=' . $this->id . '&amp;moderation=true', 'forum_forum_moderation');
			}
			else if (Http::request('moderation_unlock', 'post'))
			{
				Moderation::lock_topic($action, UNLOCK, $this->id);
				Display::message('forum_moderation_unlock', ROOT . 'index.' . PHPEXT . '?p=forum&amp;f_id=' . $this->id . '&amp;moderation=true', 'forum_forum_moderation');
			}
		}
	}

	/*
	** Qui a posté dans le sujet ?
	*/
	public function who_posted()
	{
		$topic_id = intval(Http::request('who_posted'));

		Fsb::$tpl->set_file('forum/forum_who_posted.html');

		$sql = 'SELECT u.u_id, u.u_nickname, u.u_color, COUNT(p.p_id) AS total
				FROM ' . SQL_PREFIX . 'posts p
				LEFT JOIN ' . SQL_PREFIX . 'users u
					ON u.u_id = p.u_id
				WHERE p.t_id = ' . $topic_id . '
					AND p.f_id = ' . $this->id . '
				GROUP BY u.u_id, u.u_nickname, u.u_color
				ORDER BY total DESC';
		$result = Fsb::$db->query($sql);
		while ($row = Fsb::$db->row($result))
		{
			Fsb::$tpl->set_blocks('user', array(
				'NICKNAME' =>		htmlspecialchars($row['u_nickname']),
				'COLOR' =>			($row['u_color']) ? $row['u_color'] : 'class="user"',
				'TOTAL' =>			sprintf(String::plural('forum_total_post', $row['total']), $row['total']),

				'U_PROFILE' =>		sid(ROOT . 'index.' . PHPEXT . '?p=userprofile&amp;id=' . $row['u_id']),
			));
		}
		Fsb::$db->free($result);
	}
}

/* EOF */