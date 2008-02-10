<?php
/*
** +---------------------------------------------------+
** | Name :			~/main/forum/forum_mp.php
** | Begin :		06/10/2005
** | Last :			22/12/2007
** | User :			Genova
** | Project :		Fire-Soft-Board 2 - Copyright FSB group
** | License :		GPL v2.0
** +---------------------------------------------------+
*/

/*
** Messagerie privée des membres
** La messagerie privée possède un header bien à elle en plus du header normal, ce header
** affiche les liens vers les différentes options et boites de la messagerie privé
*/
class Fsb_frame_child extends Fsb_frame
{
	// Paramètres d'affichage de la page (barre de navigation, boite de stats)
	public $_show_page_header_nav = TRUE;
	public $_show_page_footer_nav = TRUE;
	public $_show_page_stats = FALSE;

	// Boite
	public $box;
	
	// ID du message privé
	public $id;
	
	// Numéro de la page courante
	public $page;

	// Donnée du MP en cas de lecture
	public $mp_data;
	
	/*
	** Constructeur
	*/
	public function main()
	{
		// Le membre a le droit d'accéder à cette page ?
		if (!Fsb::$session->is_logged())
		{
			Http::redirect(ROOT . 'index.' . PHPEXT . '?p=login&redirect=mp');
		}

		$this->box = Http::request('box');

		if (!$this->box || !in_array($this->box, $GLOBALS['_list_box']))
		{
			$this->box = 'inbox';
		}

		// On récupère l'ID du MP passée en paramètre, puis on défini la boite en fonction de
		// cette ID. Si l'ID est nulle on affiche le contenu de la boite, sinon on récupère les
		// données du MP
		$this->id = intval(Http::request('id'));
		if ($this->id)
		{
			$this->get_read_mp_data();
		}

		$this->page = intval(Http::request('page'));
		if ($this->page <= 0)
		{
			$this->page = 1;
		}
		
		$call = new Call($this);
		$call->post(array(
			'submit_savebox' =>				':save_mp',
			'submit_delete' =>				':delete_mp',
			'submit_blacklist_add' =>		':add_user_in_blacklist',
			'submit_blacklist_delete' =>	':delete_user_from_blacklist',
			'submit_auto_answer' =>			':submit_auto_answer',
		));

		Display::header_mp($this->box);

		$call->functions(array(
			'box' => array(
				'inbox' =>		'show_message_box',
				'outbox' =>		'show_message_box',
				'save_inbox' =>	'show_message_box',
				'save_outbox' =>'show_message_box',
				'options' =>	'show_options_box',
			),
		));
	}
	
	/*
	** Affiche les boites de liste de messages (reception, envoie, etc ...)
	*/
	public function show_message_box()
	{
		// Si l'argument $id est passé on affiche le contenu du message
		if ($this->id)
		{
			$this->show_message();
			return ;
		}
		
		Fsb::$tpl->set_file('forum/forum_mp.html');
		Fsb::$tpl->set_switch('mp_list');
		
		// On construit les requètes pour récupérer les messages de la boite
		switch ($this->box)
		{
			case 'inbox' :
				$sql_mp_login_id = 'mp.mp_from';
				$sql_mp_type = MP_INBOX;
				$sql_mp_and_login_id = 'mp.mp_to = ' . Fsb::$session->id();
				$lang_send = Fsb::$session->lang('mp_send_by');

				// Si on est dans la messagerie privée, on annule l'ouverture de la popup
				if (Fsb::$session->data['u_new_mp'])
				{
					Fsb::$tpl->set_vars(array(
						'HAVE_NEW_MP' =>	FALSE,
					));
				}
				$check_max = 'inbox';
			break;
			
			case 'outbox' :
				$sql_mp_login_id = 'mp.mp_to';
				$sql_mp_type = MP_OUTBOX;
				$sql_mp_and_login_id = 'mp.mp_from = ' . Fsb::$session->id();
				$lang_send = Fsb::$session->lang('mp_send_to');
				$check_max = 'outbox';
			break;
			
			case 'save_inbox' :
				$sql_mp_login_id = 'mp.mp_from';
				$sql_mp_type = MP_SAVE_INBOX;
				$sql_mp_and_login_id = 'mp.mp_to = ' . Fsb::$session->id();
				$lang_send = Fsb::$session->lang('mp_send_by');
				$check_max = 'savebox';
			break;

			case 'save_outbox' :
				$sql_mp_login_id = 'mp.mp_to';
				$sql_mp_type = MP_SAVE_OUTBOX;
				$sql_mp_and_login_id = 'mp.mp_from = ' . Fsb::$session->id();
				$lang_send = Fsb::$session->lang('mp_send_to');
				$check_max = 'savebox';
			break;
		}

		// Recherche dans les messages privés ?
		$sql_search = '';
		if ($search = Http::request('mp_search', 'post|get'))
		{
			$words = preg_split('#[^\S]+#si', $search);
			foreach ($words AS $word)
			{
				$word = trim($word);
				if ($word && strlen($word) > 2)
				{
					$sql_search .= ' AND mp.mp_content ' . Fsb::$db->like() . ' \'%' . Fsb::$db->escape($word) . '%\'';
				}
			}
		}

		// On compte le nombre de MP
		$sql = 'SELECT COUNT(*) AS total
				FROM ' . SQL_PREFIX . 'mp mp
				WHERE mp.mp_type = ' . $sql_mp_type . '
					AND ' . $sql_mp_and_login_id
					. $sql_search;
		$total_mp = Fsb::$db->get($sql, 'total');

		// Si ce nombre de MP dépasse le quota de la page ...
		$this->delete_quota_mp($total_mp, Fsb::$cfg->get('mp_max_' . $check_max), $sql_mp_type);
		
		// Pagination des messages privés
		$per_page = 30;
		$sql_mp_limit = ($this->page - 1) * $per_page;
		if (ceil($total_mp / $per_page) > 1)
		{
			Fsb::$tpl->set_switch('mp_pagination');
		}
		
		// On assigne les variables globales du template
		Fsb::$tpl->set_vars(array(
			'PAGINATION' =>		Html::pagination($this->page, $total_mp / $per_page, ROOT . 'index.' . PHPEXT . '?p=mp&amp;box=' . $this->box . '&amp;mp_search=' . htmlspecialchars($search)),
			'BOX_NAME' =>		Fsb::$session->lang('mp_box_' . $this->box),
			'CURRENT_MP' =>		$total_mp,
			'TOTAL_MP' =>		(Fsb::$session->auth() >= MODOSUP) ? Fsb::$session->lang('unlimited') : Fsb::$cfg->get('mp_max_' . $check_max),
			'MP_SEND' =>		$lang_send,

			'U_MP_NEW' =>		sid(ROOT . 'index.' . PHPEXT .'?p=post&amp;mode=mp'),
			'U_ACTION' =>		sid(ROOT . 'index.' . PHPEXT . '?p=mp&amp;box=' . $this->box),
		));
		
		// On peut archiver uniquement dans la boite de reception / envoie
		if ($this->box == 'inbox' || $this->box == 'outbox')
		{
			Fsb::$tpl->set_switch('can_savebox');
		}

		// On affiche les messages privés
		$sql = 'SELECT mp.mp_id, mp.mp_title, mp.mp_read, mp.mp_time, ' . $sql_mp_login_id . ', u.u_id, u.u_nickname, u.u_color
				FROM ' . SQL_PREFIX . 'mp mp
				LEFT JOIN ' . SQL_PREFIX . 'users u
					ON ' . $sql_mp_login_id . ' = u.u_id
				WHERE mp.mp_type = ' . $sql_mp_type . '
					AND ' . $sql_mp_and_login_id
					. $sql_search . '
				ORDER BY mp.mp_time DESC
				LIMIT ' . $sql_mp_limit . ', ' . $per_page;
		$result = Fsb::$db->query($sql);
		while ($row = Fsb::$db->row($result))
		{
			Fsb::$tpl->set_blocks('mp', array(
				'ID' =>			$row['mp_id'],
				'NAME' =>		htmlspecialchars($row['mp_title']),
				'IMG_READ' =>	($row['mp_read']) ? TRUE : FALSE,
				'NICKNAME' =>	Html::nickname($row['u_nickname'], $row['u_id'], $row['u_color']),
				'DATE' =>		Fsb::$session->print_date($row['mp_time']),

				'U_NAME' =>		sid(ROOT . 'index.' . PHPEXT . '?p=mp&amp;id=' . $row['mp_id']),
				'U_NICKNAME' =>	sid(ROOT . 'index.' . PHPEXT . '?p=userprofile&amp;id=' . $row['u_id']),
			));
		}
		Fsb::$db->free($result);
	}

	/*
	** Supprime les MP en trop dans une boite
	** -----
	** $current_total ::			Nombre actuel de MP dans la boite
	** $quota_total ::			Quota max
	** $box ::					Boite concernée
	*/
	public function delete_quota_mp($current_total, $quota_total, $box)
	{
		if ($current_total > $quota_total && Fsb::$session->auth() < MODOSUP)
		{
			$sql_id = ($box == MP_INBOX) ? 'mp_to = ' . Fsb::$session->id() : 'mp_from = ' . Fsb::$session->id();
			$sql_limit = $current_total - $quota_total;
			$sql = 'SELECT mp_id
					FROM ' . SQL_PREFIX . 'mp
					WHERE mp_type = ' . $box . '
						AND ' . $sql_id . '
					ORDER BY mp_time ASC
					LIMIT ' . $sql_limit;
			$result = Fsb::$db->query($sql);
			$idx = array();
			while ($row = Fsb::$db->row($result))
			{
				$idx[] = $row['mp_id'];
			}
			Fsb::$db->free($result);

			if ($idx)
			{
				Moderation::delete_mp($idx);
			}
		}
	}
	
	/*
	** Archive un ou plusieurs messages dans la boite de reception
	*/
	public function save_mp()
	{		
		$action = Http::request('action', 'post');
		if ($action && is_array($action) && ($count_action = count($action)))
		{
			$action = array_map('intval', $action);
			
			// On vérifie si on a assez d'espace dans la boite d'archive pour archiver les nouveaux messages
			$sql = 'SELECT COUNT(*) AS total
					FROM ' . SQL_PREFIX . 'mp
					WHERE mp_type = ' . (($this->box == 'inbox') ? MP_SAVE_INBOX : MP_SAVE_OUTBOX) . '
						AND mp_to = ' . Fsb::$session->id();
			$result = Fsb::$db->query($sql);
			$row = Fsb::$db->row($result);
			Fsb::$db->free($result);
			
			if (($row['total'] + $count_action) > Fsb::$cfg->get('mp_max_savebox'))
			{
				Display::message('mp_savebox_full');
			}
			
			Fsb::$db->update('mp', array(
				'mp_type' =>	($this->box == 'inbox') ? MP_SAVE_INBOX : MP_SAVE_OUTBOX,
			), 'WHERE mp_type IN (' . MP_INBOX . ', ' . MP_OUTBOX . ') AND mp_id IN (' . implode(', ', $action) . ')');
		}
		
		Display::message('mp_to_savebox', ROOT . 'index.' . PHPEXT . '?p=mp&amp;box=' . $this->box, 'forum_mp');
	}

	/*
	** Affiche la page d'option pour la messagerie privée, ainsi que la blacklist et
	** le répondeur.
	*/
	public function show_options_box()
	{
		Fsb::$tpl->set_file('forum/forum_mp.html');
		Fsb::$tpl->set_switch('mp_options');

		// On créé la liste de la blacklist
		if (Fsb::$mods->is_active('mp_blacklist'))
		{
			$sql = 'SELECT bl.blacklist_id, u.u_nickname
					FROM ' . SQL_PREFIX . 'mp_blacklist bl
					LEFT JOIN ' . SQL_PREFIX . 'users u
						ON bl.blacklist_from_id = u.u_id
					WHERE bl.blacklist_to_id = ' . Fsb::$session->id() . '
					ORDER BY u.u_nickname, u.u_id';
			$result = Fsb::$db->query($sql);
			
			$list_blacklist = array();
			while ($row = Fsb::$db->row($result))
			{
				$list_blacklist[$row['blacklist_id']] = $row['u_nickname'];
			}
			$count_blacklist = count($list_blacklist);
			Fsb::$db->free($result);
		}
		else
		{
			$list_blacklist = array();
			$count_blacklist = 0;
		}
		
		// On génère les variables de template
		Fsb::$tpl->set_vars(array(
			'AUTO_ANSWER_ACTIV_YES' =>	(Fsb::$session->data['u_mp_auto_answer_activ']) ? 'checked="checked"' : '',
			'AUTO_ANSWER_ACTIV_NO' =>	(!Fsb::$session->data['u_mp_auto_answer_activ']) ? 'checked="checked"' : '',
			'AUTO_ANSWER_MESSAGE' =>	htmlspecialchars(Fsb::$session->data['u_mp_auto_answer_message']),
			'COUNT_BLACKLIST' =>		$count_blacklist,
			'LIST_BLACKLIST' =>			Html::create_list('blacklist[]', array(), $list_blacklist, 'multiple="multiple" size="' . (($count_blacklist < 5) ? $count_blacklist : 5) . '"'),

			'U_ACTION' =>				sid(ROOT . 'index.' . PHPEXT . '?p=mp&amp;box=' . $this->box),
		));
	}
	
	/*
	** Ajout d'un membre dans la blacklist des messages privés
	*/
	public function add_user_in_blacklist()
	{
		if (!Fsb::$mods->is_active('mp_blacklist'))
		{
			return ;
		}

		$u_nickname = trim(Http::request('blacklist_add', 'post'));
		
		// On récupère l'ID du membre
		$sql = 'SELECT u_id, u_auth
				FROM ' . SQL_PREFIX . 'users
				WHERE u_nickname = \'' . Fsb::$db->escape($u_nickname) . '\'';
		if (!$row = Fsb::$db->request($sql))
		{
			Display::message('mp_user_not_exists');
		}

		// Pas de blacklist pour les administrateurs / modérateurs globaux
		if ($row['u_auth'] >= MODOSUP)
		{
			Display::message('mp_cant_blacklist_admin');
		}

		// Si le membre n'étais pas déjà dans la blacklist on l'y met
		$sql = 'SELECT blacklist_from_id
				FROM ' . SQL_PREFIX . 'mp_blacklist
				WHERE blacklist_from_id = ' . $row['u_id'] . '
					AND blacklist_to_id = ' . Fsb::$session->id();
		if (!Fsb::$db->request($sql))
		{
			Fsb::$db->insert('mp_blacklist', array(
				'blacklist_from_id' =>	$row['u_id'],
				'blacklist_to_id' =>	Fsb::$session->id(),
			));
		}
		
		Display::message('mp_blacklist_well_add', ROOT . 'index.' . PHPEXT . '?p=mp&amp;box=options', 'forum_mp2');
	}
	
	/*
	** Supprime un ou plusieurs membre de la blacklist
	*/
	public function delete_user_from_blacklist()
	{
		if (!Fsb::$mods->is_active('mp_blacklist'))
		{
			return ;
		}

		$blacklist = Http::request('blacklist', 'post');
		if (!is_array($blacklist))
		{
			$blacklist = array();
		}
		$blacklist = array_map('intval', $blacklist);
		
		if (count($blacklist))
		{
			$sql = 'DELETE FROM ' . SQL_PREFIX . 'mp_blacklist
					WHERE blacklist_id IN (' . implode(', ', $blacklist) . ')
						AND blacklist_to_id = ' . Fsb::$session->id();
			Fsb::$db->query($sql);
		}
		
		Display::message('mp_blacklist_well_delete', ROOT . 'index.' . PHPEXT . '?p=mp&amp;box=options', 'forum_mp2');
	}
	
	/*
	** Vérifie les données du formulaire et sauve les modifications du répondeur
	*/
	public function submit_auto_answer()
	{
		$activ =	intval(Http::request('auto_answer_activ', 'post'));
		$message =	trim(Http::request('auto_answer_message', 'post'));
		
		if ($activ && empty($message))
		{
			Display::message('mp_auto_answer_need_message');
		}

		// On remplace les sauts de ligne par [br]
		$message = str_replace(array("\r\n", "\n"), array('[br]', '[br]'), $message);
		
		if (strlen($message) > 255)
		{
			Display::message(sprintf(Fsb::$session->lang('mp_auto_answer_error_message'), strlen($message), 255));
		}

		Fsb::$db->update('users', array(
			'u_mp_auto_answer_activ' =>	$activ,
			'u_mp_auto_answer_message' =>	substr($message, 0, 255),
		), 'WHERE u_id = ' . Fsb::$session->id());
		
		Display::message('mp_auto_answer_well', ROOT . 'index.' . PHPEXT . '?p=mp&amp;box=options', 'forum_mp2');
	}

	/*
	** Récupère les données du MP qu'on lit
	*/
	public function get_read_mp_data()
	{
		// Données du MP
		$sql = 'SELECT *
				FROM ' . SQL_PREFIX . 'mp
				WHERE mp_id = ' . $this->id;
		$result = Fsb::$db->query($sql);
		$this->mp_data = Fsb::$db->row($result);
		Fsb::$db->free($result);

		if (!$this->mp_data)
		{
			Display::message('mp_not_exists');
		}

		switch ($this->mp_data['mp_type'])
		{
			case MP_INBOX :
				$this->box = 'inbox';
				$user_key = 'mp_to';
			break;

			case MP_OUTBOX :
				$this->box = 'outbox';
				$user_key = 'mp_from';
			break;

			case MP_SAVE_INBOX :
				$this->box = 'save_inbox';
				$user_key = 'mp_to';
			break;

			case MP_SAVE_OUTBOX :
				$this->box = 'save_outbox';
				$user_key = 'mp_from';
			break;
		}

		// On vérifie si le membre a le droit de lire ce MP
		if (Fsb::$session->id() != $this->mp_data[$user_key])
		{
			Display::message('mp_not_allowed');
		}
	}

	/*
	** Affiche le message privé qu'on veut lire
	*/
	public function show_message()
	{
		// Mise à jour du nombre de messages non lus du membre
		switch ($this->mp_data['mp_type'])
		{
			case MP_INBOX :
				$user_key = 'mp_to';
				$user_key2 = 'mp_from';
				$mp_savebox = '';
				$okbox = TRUE;
				$type = MP_INBOX;
				$type2 = MP_OUTBOX;
				Fsb::$tpl->set_switch('can_savebox');
			break;

			case MP_OUTBOX :
				$user_key = 'mp_from';
				$user_key2 = 'mp_to';
				$mp_savebox = '';
				$type = MP_OUTBOX;
				$type2 = MP_INBOX;
				$okbox = FALSE;
				Fsb::$tpl->set_switch('can_savebox');
			break;

			case MP_SAVE_INBOX :
				$user_key = 'mp_to';
				$user_key2 = 'mp_from';
				$okbox = TRUE;
				$mp_savebox = 'OR (mp.mp_type = ' . MP_SAVE_INBOX . ' AND mp.' . $user_key . ' = ' . Fsb::$session->id() . ')';
				$type = MP_INBOX;
				$type2 = MP_OUTBOX;
			break;

			case MP_SAVE_OUTBOX :
				$user_key = 'mp_from';
				$user_key2 = 'mp_to';
				$okbox = FALSE;
				$mp_savebox = 'OR (mp.mp_type = ' . MP_SAVE_OUTBOX . ' AND mp.' . $user_key . ' = ' . Fsb::$session->id() . ')';
				$type = MP_OUTBOX;
				$type2 = MP_INBOX;
			break;
		}

		if ($this->mp_data['mp_read'] == MP_UNREAD && $okbox)
		{
			// Mise à jour du MP en lu
			$in = $this->id;
			if (!$this->mp_data['is_auto_answer'])
			{
				$in .= ', ' . ($this->id + 1);
			}

			Fsb::$db->update('mp', array(
       				'mp_read' => MP_READ,
       		), 'WHERE mp_id IN (' . $in . ')');

			Fsb::$db->update('users', array(
					'u_total_mp' => Fsb::$session->data['u_total_mp'] - 1,
			), 'WHERE u_id = ' . $this->mp_data[$user_key]);
		}

		// Clause SQL pour les enfants
		if ($this->mp_data['mp_parent'] > 0)
		{
			$sql_parent = '(mp.mp_parent = ' . $this->mp_data['mp_parent'] . ' OR mp.mp_id IN (' . $this->mp_data['mp_parent'] . ', ' . ($this->mp_data['mp_parent'] + 1) . '))';
		}
		else
		{
			$sql_parent = 'mp.mp_id = ' . $this->id;
		}

		$parser = new Parser();

		// Total de messages dans cette discussion privées
		$per_page = 15;
		$sql = 'SELECT COUNT(*) AS total
				FROM ' . SQL_PREFIX . 'mp mp
				WHERE mp.mp_time <= ' . $this->mp_data['mp_time'] . '
					AND ((mp.mp_type = ' . $type . ' AND mp.' . $user_key . ' = ' . Fsb::$session->id() . ')
						' . $mp_savebox . '
						OR (mp.mp_type = ' . $type2 . ' AND mp.' . $user_key2 . ' = ' . Fsb::$session->id() . '))
					AND ' . $sql_parent;
		$total_posts = Fsb::$db->get($sql, 'total');

		Fsb::$tpl->set_file('forum/forum_mp_message.html');
		Fsb::$tpl->set_vars(array(
			'MP_ID' =>			$this->id,
			'MP_TITLE' =>		htmlspecialchars($this->mp_data['mp_title']),
			'MP_CAN_REPLY' =>	($this->box != 'outbox' && $this->box != 'save_outbox' && $this->mp_data['mp_from'] != VISITOR_ID) ? TRUE : FALSE,
			'PAGINATION' =>		(ceil($total_posts / $per_page) > 1) ? Html::pagination($this->page, $total_posts / $per_page, ROOT . 'index.' . PHPEXT . '?p=mp&amp;box=' . $this->box . '&amp;id=' . $this->id) : '',
			
			'U_MP_NEW' =>		sid(ROOT . 'index.' . PHPEXT . '?p=post&amp;mode=mp'),
			'U_MP_REPLY' =>		sid(ROOT . 'index.' . PHPEXT . '?p=post&amp;mode=mp&amp;id=' . $this->id),
			'U_MP_QUOTE' =>		sid(ROOT . 'index.' . PHPEXT . '?p=post&amp;mode=mp&amp;quote=true&amp;id=' . $this->id),
		));

		// On affiche le contenu du MP, avec les messages enfants
		$sql = 'SELECT mp.*, u.u_id, u.u_nickname, u.u_color, u.u_avatar, u.u_can_use_avatar, u.u_avatar_method, u.u_auth
				FROM ' . SQL_PREFIX . 'mp mp
				LEFT JOIN ' . SQL_PREFIX . 'users u
					ON u.u_id = mp.mp_from
				WHERE mp.mp_time <= ' . $this->mp_data['mp_time'] . '
					AND ((mp.mp_type = ' . $type . ' AND mp.' . $user_key . ' = ' . Fsb::$session->id() . ')
						' . $mp_savebox . '
						OR (mp.mp_type = ' . $type2 . ' AND mp.' . $user_key2 . ' = ' . Fsb::$session->id() . '))
					AND ' . $sql_parent . '
				ORDER BY mp.mp_time DESC, mp.mp_id ASC
				LIMIT ' . (($this->page - 1) * $per_page) . ', ' . $per_page;
		$result = Fsb::$db->query($sql);
		$show_parent = FALSE;
		while ($row = Fsb::$db->row($result))
		{
			// Corrige un bug affichant deux fois le parent lorsqu'on s'auto envoie un MP
			if ($row['mp_id'] == $this->mp_data['mp_parent'] || $row['mp_id'] == $this->mp_data['mp_parent'] + 1)
			{
				if ($show_parent)
				{
					continue;
				}
				$show_parent = TRUE;
			}

			// Avatar du membre
			$u_avatar = User::get_avatar($row['u_avatar'], $row['u_avatar_method'], $row['u_can_use_avatar']);

			// Parse du HTML ?
			$parser->parse_html = (Fsb::$cfg->get('activate_html') && $row['u_auth'] >= MODOSUP) ? TRUE : FALSE;

			Fsb::$tpl->set_blocks('mp', array(
				'CONTENT' =>		$parser->mapped_message($row['mp_content'], 'classic'),
				'NICKNAME' =>		Html::nickname($row['u_nickname'], $row['u_id'], $row['u_color']),
				'DATE' =>			Fsb::$session->print_date($row['mp_time']),
				'IP' =>				(Fsb::$session->is_authorized('auth_ip')) ? $row['u_ip'] : NULL,

				'U_EDIT_MP' =>		($row['mp_read'] == MP_UNREAD) ? sid(ROOT . 'index.' . PHPEXT . '?p=post&amp;mode=edit_mp&amp;id=' . $row['mp_id']) : '',
				'U_AVATAR' =>		$u_avatar,
				'U_IP' =>			sid(ROOT . 'index.' . PHPEXT . '?p=modo&amp;module=ip&amp;ip=' . $row['u_ip']),
				'U_ABUSE' =>		sid(ROOT . 'index.' . PHPEXT . '?p=abuse&amp;mode=mp&amp;id=' . $row['mp_id']),
			));
		}
		Fsb::$db->free($result);
	}
	
	/*
	** Supprime un ou plusieurs messages privés et redirige vers la boîte de réception
	*/
	public function delete_mp()
	{
		$action = Http::request('action', 'post');
		if (check_confirm())
		{
			if ($action && is_array($action))
			{
				$action = array_map('intval', $action);

				switch ($this->box)
				{
					case 'inbox' :
						$user_key = 'mp_to';
						$type = MP_INBOX;
					break;

					case 'outbox' :
						$user_key = 'mp_from';
						$type = MP_OUTBOX;
					break;

					case 'save_inbox' :
						$user_key = 'mp_to';
						$type = MP_SAVE_INBOX;
					break;

					case 'save_outbox' :
						$user_key = 'mp_from';
						$type = MP_SAVE_OUTBOX;
					break;
				}

				// Filtre des MP à supprimer
				$sql = 'SELECT mp_id, mp_read
						FROM ' . SQL_PREFIX . 'mp
						WHERE mp_id IN (' . implode(', ', $action) . ')
							AND ' . $user_key . ' = ' . Fsb::$session->id() . '
							AND mp_type = ' . $type;
				$idx = array();
				$total_unread = 0;
				$result = Fsb::$db->query($sql);
				while ($row = Fsb::$db->row($result))
				{
					$idx[] = $row['mp_id'];
					if (in_array($this->box, array('inbox', 'save_inbox')) && $row['mp_read'] == MP_UNREAD)
					{
						$total_unread++;
					}
				}
				Fsb::$db->free($result);

				Fsb::$db->update('users', array(
						'u_total_mp' => Fsb::$session->data['u_total_mp'] - $total_unread,
				), 'WHERE  u_id = ' . Fsb::$session->id());

				Moderation::delete_mp($idx);
			}
			Display::message('mps_deleted', ROOT . 'index.' . PHPEXT . '?p=mp&amp;box=' . $this->box, 'forum_mp');
		}
		else if (Http::request('confirm_no', 'post'))
		{
			Http::redirect(ROOT . 'index.' . PHPEXT . '?p=mp&box=' . $this->box);
		}
		else
		{
			Display::confirmation(Fsb::$session->lang('mp_delete_confirm'), ROOT . 'index.' . PHPEXT . '?p=mp&amp;box=' . $this->box, array('action' => $action, 'submit_delete' => TRUE));
		}
	}
}

/* EOF */