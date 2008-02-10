<?php
/*
** +---------------------------------------------------+
** | Name :		~/main/class/class_display.php
** | Begin :	19/06/2007
** | Last :		13/12/2007
** | User :		Genova
** | Project :	Fire-Soft-Board 2 - Copyright FSB group
** | License :	GPL v2.0
** +---------------------------------------------------+
*/

/*
** Affichage de messages sur le forum (erreurs, confirmations, etc..)
*/
class Display extends Fsb_model
{
	/*
	** Handler lié à trigger_error(). Deux constantes liées au forum ont été crées :
	**	FSB_ERROR pour les messages d'erreur critique
	**	FSB_MESSAGE pour les messages d'information
	** -----
	** $errno ::	Code de l'erreur
	** $errstr ::	Nom de l'erreur
	** $errfile ::	Fichier où l'erreur se situe
	** $errline ::	Ligne où l'erreur se situe
	*/
	public static function error_handler($errno, $errstr, $errfile, $errline)
	{
		if (!(error_reporting() & $errno))
		{
			return ;
		}

		switch ($errno)
		{
			case E_NOTICE :
				echo '<b>FSB Notice : <i>' . $errstr . '</i> in file <i>' . $errfile . '</i> (<i>' . $errline . '</i>)</b><br />';
			break;

			case E_WARNING :
				echo '<b>FSB Warning : <i>' . $errstr . '</i> in file <i>' . $errfile . '</i> (<i>' . $errline . '</i>)</b><br />';
			break;

			case FSB_ERROR :
				// Affichage de l'erreur fatale
				echo "<html><head><title>FSB2 :: Erreur</title><meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" /></head><body>
				<style>pre { border: 1px #000000 dashed; background-color: #EEEEEE; padding: 10px; }</style>
				Une erreur a été rencontrée durant l'éxécution du script.";

				// Débugage possible ?
				if (Fsb::$debug->can_debug)
				{
					// Affichage plus précis de certaines erreurs
					if (preg_match('#^error_sql #i', $errstr) || preg_match('#^Call to undefined method #i', $errstr))
					{
						$sql_backtrace = debug_backtrace();
						if (isset($sql_backtrace[3]))
						{
							$index = 3;
							if (!isset($sql_backtrace[3]['line']) && isset($sql_backtrace[4]))
							{
								$index = 4;
							}
							$errline = $sql_backtrace[$index]['line'];
							$errfile = $sql_backtrace[$index]['file'];
						}
						unset($sql_backtrace);
					}

					echo " L'erreur rencontrée est :<pre>" . $errstr . "</pre>
					à la ligne <i><b>$errline</b></i> du fichier <i><b>$errfile</b></i>";

					$fsb_path = (Fsb::$cfg) ? Fsb::$cfg->get('fsb_path') : './';
					if (file_exists(ROOT . fsb_basename($errfile, $fsb_path)))
					{
						echo "<br /><br />Voici la zone où se situe l'erreur dans le script :";
						$content = file(ROOT . fsb_basename($errfile, Fsb::$cfg->get('fsb_path')));
						$count_content = count($content);
						$begin = ($errline < 7) ? 0 : $errline - 7;
						echo '<pre>';
						for ($i = $begin; $i < $count_content && $i <= ($begin + 14); $i++)
						{
							echo '<b>Ligne ' . $i . ' :</b> ' . htmlspecialchars($content[$i]);
						}
						echo '</pre>';
					}

					// Débugage avancé via un trace des fonctions / méthodes appelées
					echo '<br />Trace des fonctions / méthodes appelées :<br /><pre>';
					$back = debug_backtrace();
					$count_back = count($back);
					for ($i = ($count_back - 1); $i > 0; $i--)
					{
						echo  ((isset($back[$i]['class'])) ? "<b>Méthode :</b>\t" . $back[$i]['class'] . $back[$i]['type'] : "<b>Fonction :</b>\t") . $back[$i]['function'] . "()\n";
						echo (isset($back[$i]['file'])) ? "<b>Fichier :</b>\t" . fsb_basename($back[$i]['file'], $fsb_path) . "\n" : '';
						echo (isset($back[$i]['line'])) ? "<b>Ligne :</b>\t\t" . $back[$i]['line'] : '';
						if ($i > 1)
						{
							echo "\n\n\n";
						}
					}
					echo '</pre>';
				}
				else
				{
					echo "<br /><br /><b>Le mode DEBUG est désactivé, veuillez contacter l'administrateur du forum.<br />
					DEBUG mode is disactivated, please contact the forum's administrator.</b>";
				}
				echo '</body></html>';

				if (defined('FSB_INSTALL'))
				{
					Log::add_custom(Log::ERROR, $errstr, array(), $errline, $errfile);
				}
				exit;
			break;

			case FSB_MESSAGE :
				// Message d'information
				Fsb::$tpl->set_file('error_handler.html');
				Fsb::$tpl->set_vars(array(
					'HANDLER_USE_FOOTER' =>	TRUE,
					'CONTENT' =>			(Fsb::$session->lang($errstr)) ? Fsb::$session->lang($errstr) : $errstr,
				));

				$GLOBALS['_show_page_header_nav'] = TRUE;
				$GLOBALS['_show_page_footer_nav'] = FALSE;
				$GLOBALS['_show_page_stats'] = FALSE;
				if (defined('FORUM'))
				{
					Fsb::$frame->frame_header();
				}
				Fsb::$frame->frame_footer();

				exit;
			break;
		}
	}


	/*
	** Affiche un simple message d'information, suivit d'un message de redirection
	** Cette fonction prend en compte la configuration de redirection du membre, c'est à
	** dire que dans son profil le membre peut définir s'il souhaite être redirigé après 
	** les messages d'informations
	** -----
	** $1 ::		Message à afficher
	** $2 ::		URL de redirection
	** $3 ::		Nom de la page de la frame
	** $... ::		La suite des arguments doit etre une répétition $2, $3. Par exemple :
	**					Display::message('message', 'url', 'frame', 'url', 'frame', 'url', 'frame');
	*/
	public static function message()
	{
		$str = func_get_arg(0);
		$str_add = '';
		if (func_num_args() > 1)
		{
			$url = func_get_arg(1);
			$str_add = '';

			// Redirection après le message d'erreur ?
			if (Fsb::$session->data['u_activate_redirection'] & 4)
			{
				Http::redirect(str_replace('&amp;', '&', $url), 0);
			}
			else if (Fsb::$session->data['u_activate_redirection'] & 8)
			{
				Http::redirect($url, 3);
				$str_add = '<br /><br />' . sprintf(Fsb::$session->lang('you_will_be_redirected'), 3);
			}
		}

		if (!defined('FSB_INSTALL'))
		{
			trigger_error($str, FSB_ERROR);
		}

		$content = '';
		for ($i = 1; $i < func_num_args(); $i += 2)
		{
			$arg1 = func_get_arg($i);
			$arg2 = func_get_arg($i + 1);
			$content .= return_to($arg1, $arg2);
		}

		// Affichage du message d'erreur
		trigger_error(((Fsb::$session->lang($str)) ? Fsb::$session->lang($str) : $str) . $content . $str_add, FSB_MESSAGE);
	}

	/*
	** Affiche une boite de confirmation oui / non
	** -----
	** $str ::	Question de confirmation
	** $url ::	URL de redirection de la confirmation
	** $hidden ::	Tableau de champs HIDDEN a passer au formulaire
	*/
	public static function confirmation($str, $url, $hidden = array())
	{		
		Fsb::$tpl->set_file('confirmation.html');
		Fsb::$tpl->set_vars(array(
			'STR_CONFIRM' =>	$str,

			'U_ACTION' =>		sid($url),
		));

		// On ajoute dans le champs hidden une variable fsb_check_sid, qui contiendra la SID du membre
		// appelant cette page de confirmation. La réussite de la confirmation doit ensuite se faire
		// avec la fonction check_confirm(), qui vérifiera si la SID est bonne. Le but étant de protéger
		// la confirmation en évitant a des scripts malicieux de forcer l'administrateur a confirmer
		// des actions automatiquements.
		$hidden['fsb_check_sid'] = Fsb::$session->sid;

		// On créé le code HTML des champs hidden	
		foreach ($hidden AS $name => $value)
		{
			if (is_array($value))
			{
				foreach ($value AS $subvalue)
				{
					Fsb::$tpl->set_blocks('hidden', array(
						'NAME' =>		$name . '[]',
						'VALUE' =>		$subvalue,
					));
				}
			}
			else
			{
				Fsb::$tpl->set_blocks('hidden', array(
					'NAME' =>		$name,
					'VALUE' =>		$value,
				));
			}
		}

		if (defined('FORUM') || defined('IN_ADM'))
		{
			Fsb::$frame->frame_footer();
		}
		exit;
	}

	/*
	** Affiche un formulaire pour entrer les identifiants FTP
	*/
	public static function check_ftp()
	{
		// Si on a entré les identifiants dans la configuration
		if (Fsb::$cfg->get('ftp_default'))
		{
			return (array(
				'host' =>		Fsb::$cfg->get('ftp_host'),
				'login' =>		Fsb::$cfg->get('ftp_login'),
				'password' =>	Fsb::$cfg->get('ftp_password'),
				'port' =>		Fsb::$cfg->get('ftp_port'),
				'path' =>		Fsb::$cfg->get('ftp_path'),
			));
		}

		// Si les identifiants ont été envoyés on les retourne
		if (Http::request('ftp_submit', 'post'))
		{
			$password = trim(Http::request('ftp_password', 'post'));
			$data = array(
				'host' =>		trim(Http::request('ftp_host', 'post')),
				'login' =>		trim(Http::request('ftp_login', 'post')),
				'path' =>		trim(Http::request('ftp_path', 'post')),
				'port' =>		intval(Http::request('ftp_port', 'post')),
			);

			// Si la case ftp_remind a été cochée on garde l'hote, le login et le port en mémoire. Pour des raisons de
			// sécurité on ne gardera pas le mot de passe en mémoire
			if (Http::request('ftp_remind', 'post'))
			{
				Http::cookie('ftp', serialize($data), CURRENT_TIME + ONE_YEAR);
			}
			else
			{
				Http::cookie('ftp', '', CURRENT_TIME);
			}

			$data['password'] = $password;
			return ($data);
		}

		// Sinon on affiche le formulaire
		Fsb::$tpl->set_file('handler_ftp.html');

		// On met les anciennes valeurs de POST en champs hidden
		foreach ($_POST AS $key => $value)
		{
			if (is_array($value))
			{
				foreach ($value AS $subkey => $subvalue)
				{
					Fsb::$tpl->set_blocks('ftp_hidden', array(
						'NAME' =>	$key . '[' . $subkey . ']',
						'VALUE' =>	htmlspecialchars($subvalue),
					));
				}
			}
			else
			{
				Fsb::$tpl->set_blocks('ftp_hidden', array(
					'NAME' =>	$key,
					'VALUE' =>	htmlspecialchars($value),
				));
			}
		}

		$ftp_host = $ftp_login = '';
		$ftp_port = '21';
		$ftp_path = dirname($_SERVER['SCRIPT_NAME']);
		if (defined('IN_ADM'))
		{
			// Dans l'administration on supprime le répertoire admin/
			$ftp_path = dirname($ftp_path);
		}

		// Valeur gardées en mémoires dans un cookie ?
		if ($cookie = Http::getcookie('ftp'))
		{
			$cookie = unserialize($cookie);
			if (is_array($cookie))
			{
				$ftp_host = $cookie['host'];
				$ftp_login = $cookie['login'];
				$ftp_port = $cookie['port'];
				$ftp_path = $cookie['path'];
			}
		}

		// Variables normales
		Fsb::$tpl->set_vars(array(
			'FTP_HOST' =>		$ftp_host,
			'FTP_LOGIN' =>		$ftp_login,
			'FTP_PORT' =>		$ftp_port,
			'FTP_PATH' =>		$ftp_path,
			'FTP_REMIND' =>		(is_array($cookie)) ? TRUE : FALSE,

			'U_ACTION' =>		sid(((defined('FORUM')) ? ROOT : '') . 'index.' . PHPEXT . '?' . htmlspecialchars($_SERVER['QUERY_STRING'])),
		));

		Fsb::$frame->frame_footer();
		exit;
	}

	/*
	** Génère l'affichage des FSBcodes
	** -----
	** $in_sig ::		TRUE si on est dans l'édition de signature
	*/
	public static function fsbcode($in_sig = FALSE)
	{
		$sql = 'SELECT fsbcode_tag, fsbcode_img, fsbcode_javascript, fsbcode_description, fsbcode_list
				FROM ' . SQL_PREFIX . 'fsbcode
				WHERE fsbcode_activated' . (($in_sig) ? '_sig' : '') . ' = 1
				ORDER BY fsbcode_order';
		$result = Fsb::$db->query($sql, 'fsbcode_');
		while ($row = Fsb::$db->row($result))
		{
			$list = trim($row['fsbcode_list']);
			$code = $row['fsbcode_tag'];

			// Si on empèche les colorateurs de syntaxe d'être utilisés, la liste CODE n'a plus
			// aucune sens donc on le converti en fsbcode normal
			if ($code == 'code' && !Fsb::$mods->is_active('highlight_code'))
			{
				$list = '';
			}

			// Upload activée ?
			if ($code == 'attach' && (!Fsb::$session->is_authorized('upload_file') || !Fsb::$mods->is_active('upload')))
			{
				continue ;
			}

			// Simple Fsbcode ...
			if (!$list)
			{
				Fsb::$tpl->set_blocks('fsbcode', array(
					'CODE' =>		$code,
					'IMG' =>		($row['fsbcode_img']) ? ROOT . 'tpl/' . Fsb::$session->data['u_tpl'] . '/img/fsbcode/' . $row['fsbcode_img'] : '',
					'OPEN' =>		'[' . $code . ']',
					'TEXT' =>		($row['fsbcode_description']) ? htmlspecialchars($row['fsbcode_description']) : Fsb::$session->lang('fsbcode_' . $code),
					'CLOSE' =>		'[/' . $code . ']',
					'FCT' =>		$row['fsbcode_javascript'],
				));
			}
			// ... ou liste ?
			else
			{
				Fsb::$tpl->set_blocks('fsbcode_list', array(
					'CODE' =>		$code,
					'TEXT' =>		($row['fsbcode_description']) ? htmlspecialchars($row['fsbcode_description']) : Fsb::$session->lang('fsbcode_' . $code),
					'FCT' =>		$row['fsbcode_javascript'],
				));
			
				Fsb::$tpl->set_blocks('fsbcode_list.item', array(
					'VALUE' =>		0,
					'LANG' =>		(Fsb::$session->lang('fsbcode_' . $code)) ? Fsb::$session->lang('fsbcode_' . $code) : $code,
				));

				$style = NULL;
				foreach (explode("\n", $list) AS $i => $line)
				{
					$line = trim($line);
					if ($i == 0 && preg_match('#^style=(.*?)$#i', $line, $m))
					{
						$style = $m[1];
					}
					else
					{
						Fsb::$tpl->set_blocks('fsbcode_list.item', array(
							'VALUE' =>		$line,
							'STYLE' =>		($style) ? sprintf('style="%s"', sprintf($style, $line)) : '',
							'LANG' =>		(Fsb::$session->lang('fsbcode_item_' . $code . '_' . $line)) ? Fsb::$session->lang('fsbcode_item_' . $code . '_' . $line) : $line,
						));
					}
				}
			}
		}
		Fsb::$db->free($result);
	}

	/*
	** Génère l'affichage des smilies
	*/
	public static function smilies()
	{
		$sql = 'SELECT sc.*, s.*
					FROM ' . SQL_PREFIX . 'smilies_cat sc
					LEFT JOIN ' . SQL_PREFIX . 'smilies s
						ON sc.cat_id = s.smiley_cat
					ORDER BY sc.cat_order, s.smiley_order';
		$result = Fsb::$db->query($sql, 'smilies_');
		$last = NULL;
		while ($row = Fsb::$db->row($result))
		{
			if ($last === NULL || $row['cat_id'] != $last['cat_id'])
			{
				Fsb::$tpl->set_blocks('smiley_cat', array(
					'CAT_ID' =>		$row['cat_id'],
					'CAT_NAME' =>	htmlspecialchars($row['cat_name']),
				));
			}

			if ($row['smiley_id'] !== NULL)
			{
				Fsb::$tpl->set_blocks('smiley_cat.smiley', array(
					'URL' =>		substr(SMILEY_PATH, strlen(ROOT)) . addslashes($row['smiley_name']),
					'TEXT' =>		addslashes(htmlspecialchars($row['smiley_tag'])),
					'TAG' =>		addslashes(addslashes(htmlspecialchars($row['smiley_tag']))),
				));
			}
			$last = $row;
		}
		Fsb::$db->free($result);

		Fsb::$tpl->set_vars(array(
			'CFG_FSB_PATH' =>	addslashes(Fsb::$cfg->get('fsb_path')) . '/',
		));
	}

	/*
	** Vérifie si le membre a accès au forum protégé par un mot de passe
	** Si ce n'est pas le cas on affiche le formulaire du mot de passe
	** -----
	** $f_id ::		ID du forum
	** $password ::	Mot de passe du forum
	** $action ::	Action pour le formulaire
	*/
	public static function forum_password($f_id, $password, $action)
	{
		if (!Fsb::$session->data['s_forum_access'] || !in_array($f_id, (array)explode(',', Fsb::$session->data['s_forum_access'])))
		{
			// Vérification du mot de passe entré
			if (Http::request('submit_forum_password', 'post'))
			{
				$submited_password = trim(Http::request('forum_password', 'post'));
				if ($submited_password && (string)$submited_password === (string)$password)
				{
					// Mot de passe corect, mise à jour de la session
					Fsb::$db->update('sessions', array(
						's_forum_access' =>		Fsb::$session->data['s_forum_access'] . ((Fsb::$session->data['s_forum_access']) ? ',' : '') . $f_id,
					), 'WHERE s_sid = \'' . Fsb::$db->escape(Fsb::$session->sid) . '\'');

					return (TRUE);
				}

				// Mot de passe incorect ...
				Fsb::$tpl->set_switch('bad_password');
			}

			// Formulaire de mot de passe
			Fsb::$tpl->set_file('display_password.html');
			Fsb::$tpl->set_vars(array(
				'U_ACTION' =>		sid($action),
			));

			return (FALSE);
		}
		else
		{
			// Accès autorisé
			return (TRUE);
		}
	}

	/*
	** Ajoute un système d'onglet sur une page
	** -----
	** $module_list ::		Liste des onglets
	** $current_module ::	Onglet selectionné
	** $url ::				URL du lien
	** $prefix_lang ::		Préfixe pour la clef de langue
	*/
	public static function header_module($module_list, $current_module, $url, $prefix_lang = '')
	{
		$width = floor(100 / count($module_list));
		foreach ($module_list AS $module)
		{
			Fsb::$tpl->set_blocks('module_header', array(
				'WIDTH' =>			$width,
				'SELECTED' =>		($module == $current_module) ? TRUE : FALSE,
				'URL' =>			sid($url . '&amp;module=' . $module),
				'NAME' =>			Fsb::$session->lang($prefix_lang . $module),
			));
		}
		Fsb::$tpl->set_switch('use_module_page');
	}

	/*
	** Header pour la messagerie privée
	** -----
	** $box ::	Boite courante
	*/
	public static function header_mp($box)
	{
		Fsb::$tpl->set_switch('show_mp_header');
		Fsb::$tpl->set_vars(array(
			'MENU_HEADER_TITLE' =>		Fsb::$session->lang('mp_panel'),
		));

		foreach ($GLOBALS['_list_box'] AS $box_name)
		{
			Fsb::$tpl->set_switch('show_menu_panel');
			Fsb::$tpl->set_blocks('module', array(
				'IS_SELECT' =>	($box == $box_name) ? TRUE : FALSE,
				'NAME' =>		Fsb::$session->lang('mp_box_' . $box_name),
				'URL' =>		sid(ROOT . 'index.' . PHPEXT . '?p=mp&amp;box=' . $box_name),
			));
		}
	}
}

/* EOF */