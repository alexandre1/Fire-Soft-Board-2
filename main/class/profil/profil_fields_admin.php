<?php
/*
** +---------------------------------------------------+
** | Name :		~/main/class/profil/profil_fields_admin.php
** | Begin :	19/09/2005
** | Last :		17/10/2007
** | User :		Genova
** | Project :	Fire-Soft-Board 2 - Copyright FSB group
** | License :	GPL v2.0
** +---------------------------------------------------+
*/

/*
** Classe permettant de gérer la création de champs personels
*/
class Profil_fields_admin extends Profil_fields
{
	/*
	** Génère les switch utilisés pour la création de ce champ de profil
	** -----
	** $type ::		Type du champ de profil
	*/
	public static function form(&$type)
	{
		if (!isset(self::$type[$type]))
		{
			$type = self::TEXT;
		}

		foreach (array_keys(self::$type[$type]) AS $key)
		{
			Fsb::$tpl->set_switch('profile_field_' . $key);
		}
	}

	/*
	** Validation de la création du champ
	** -----
	** $type ::		Type du champ de profil
	** $errstr ::	Tableau d'erreurs rencontrées pendant la validation
	*/
	public static function validate($type, &$errstr)
	{
		if (!isset(self::$type[$type]))
		{
			$type = self::TEXT;
		}

		$return = array();
		$return['pf_html_type'] =	$type;
		$return['pf_regexp'] =		Http::request('pf_regexp', 'post');
		$return['pf_lang'] =		trim(Http::request('pf_lang', 'post'));
		$return['pf_output'] =		trim(Http::request('pf_output', 'post'));
		$return['pf_topic'] =		intval(Http::request('pf_topic', 'post'));
		$return['pf_register'] =	intval(Http::request('pf_register', 'post'));
		$return['pf_maxlength'] =	intval(Http::request('pf_maxlength', 'post'));
		$return['pf_sizelist'] =	intval(Http::request('pf_sizelist', 'post'));
		$return['pf_list'] =		array();
		
		// Groupes visibles
		$return['pf_groups'] = (array) Http::request('pf_groups', 'post');
		$return['pf_groups'] = array_map('intval', $return['pf_groups']);
		$return['pf_groups'] = implode(',', $return['pf_groups']);

		// Vérification des erreurs
		if (!$return['pf_lang'])
		{
			$errstr[] = Fsb::$session->lang('adm_pf_need_lang');
		}
		
		if (isset(self::$type[$type]['maxlength']) && ($return['pf_maxlength'] <= self::$type[$type]['maxlength']['min'] || $return['pf_maxlength'] > self::$type[$type]['maxlength']['max']))
		{
			$errstr[] = sprintf(Fsb::$session->lang('adm_pf_bad_maxlength'), self::$type[$type]['maxlength']['min'], self::$type[$type]['maxlength']['max']);
		}

		if (isset(self::$type[$type]['regexp']) && @preg_match('#' . str_replace('#', '\#', $return['pf_regexp']) . '#i', 'foo') === FALSE)
		{
			$errstr[] = Fsb::$session->lang('adm_pf_bad_regexp');
		}

		if (isset(self::$type[$type]['list']))
		{
			$return['pf_list'] = trim(Http::request('pf_list', 'post'));
			if (!$return['pf_list'])
			{
				$errstr[] = Fsb::$session->lang('adm_pf_need_list');
			}
			else
			{
				// Suppression des lignes vides
				$return['pf_list'] = array_map('trim', explode("\n", $return['pf_list']));
				$new = array();
				foreach ($return['pf_list'] AS $key => $value)
				{
					if ($value)
					{
						$new[] = $value;
					}
				}
				$return['pf_list'] = $new;
			}
		}

		return ($return);
	}

	/*
	** Créé un nouveau champ de profil
	** -----
	** $field_type ::	Type de champ
	** $data ::			Informations sur le champ de profil
	*/
	public static function add($field_type, $data)
	{
		switch (SQL_DBAL)
		{
			case 'mysql' :
			case 'mysqli' :
				$method = 'add_column_mysql';
			break;

			case 'pgsql' :
				$method = 'add_column_pgsql';
			break;

			case 'sqlite' :
				$method = 'add_column_sqlite';
			break;
		}
		
		// on récupère l'ordre maximale pour placer le nouveau champ
		$sql = 'SELECT MAX(pf_order) AS max_order
				FROM ' . SQL_PREFIX . 'profil_fields
				WHERE pf_type = ' . $field_type . '
				LIMIT 1';
		$data['pf_order'] = Fsb::$db->get($sql, 'max_order') + 1;
		$data['pf_type'] = $field_type;
		$data['pf_list'] = serialize($data['pf_list']);

		Fsb::$db->insert('profil_fields', $data);

		switch ($field_type)
		{
			case PROFIL_FIELDS_CONTACT :
				$tablename = 'users_contact';
				$sql_field_name = 'contact_';
			break;
			
			case PROFIL_FIELDS_PERSONAL :
				$tablename = 'users_personal';
				$sql_field_name = 'personal_';
			break;
		}
		Profil_fields_admin::$method(SQL_PREFIX . $tablename, $sql_field_name, $data['pf_html_type'], Fsb::$db->last_id());
	}

	/*
	** Ajoute une colone dans une table mysql
	** -----
	** $tablename ::		Nom de la table
	** $sql_field_name ::	Préfixe du nom du champ
	** $type ::				Type du champ
	** $last_id ::			ID pour le nom du champ
	*/
	private static function add_column_mysql($tablename, $sql_field_name, $type, $last_id)
	{
		$sql_alter = 'ALTER TABLE ' . $tablename . ' ADD ' . Fsb::$db->escape($sql_field_name . $last_id);

		switch ($type)
		{
			case self::TEXT :
				$sql_alter .= ' VARCHAR(255) NOT NULL';
			break;
			
			case self::TEXTAREA :
				$sql_alter .= ' TEXT NOT NULL';
			break;
			
			case self::RADIO :
			case self::SELECT :
				$sql_alter .= ' TINYINT NOT NULL';
			break;
			
			case self::MULTIPLE :
				$sql_alter .= ' VARCHAR(255)';
			break;
		}
		Fsb::$db->query($sql_alter);
	}

	/*
	** Ajoute une colone dans une table PostgreSQL
	** -----
	** $tablename ::		Nom de la table
	** $sql_field_name ::	Préfixe du nom du champ
	** $type ::				Type HTML du champ
	** $last_id ::			ID pour le nom du champ
	*/
	private static function add_column_pgsql($tablename, $sql_field_name, $type, $last_id)
	{
		// On construit le début de la requète ALTER
		$sql_alter = 'ALTER TABLE ' . $tablename;
		
		// On récupère le nom du champ à créer, à partir de la dernière ID créé
		$sql_alter .= ' ADD ' . Fsb::$db->escape($sql_field_name . $last_id);
		
		// On créé le type du champ dans la requète
		switch ($type)
		{
			case self::TEXT :
				$sql_alter .= ' VARCHAR(255)';
			break;
			
			case self::TEXTAREA :
				$sql_alter .= ' TEXT';
			break;
			
			case self::RADIO :
			case self::SELECT :
				$sql_alter .= ' INT2';
			break;
			
			case self::MULTIPLE :
				$sql_alter .= ' VARCHAR(255)';
			break;
		}

		// On lance la requète ALTER
		Fsb::$db->query($sql_alter);
	}

	/*
	** Ajoute une colone dans une table SQLITE
	** -----
	** $tablename ::		Nom de la table
	** $sql_field_name ::	Préfixe du nom du champ
	** $type ::				Type HTML du champ
	** $last_id ::			ID pour le nom du champ
	*/
	private static function add_column_sqlite($tablename, $sql_field_name, $type, $last_id)
	{
		Fsb::$db->alter($tablename, 'ADD', $sql_field_name . $last_id);
	}

	/*
	** Met à jour un champ du profil
	** -----
	** $field_id ::				ID du champ de profil
	** $data ::					Informations sur la mise à jour
	*/
	public static function update($field_id, $data)
	{
		unset($data['pf_type']);
		$data['pf_list'] = serialize($data['pf_list']);
		Fsb::$db->update('profil_fields', $data, 'WHERE pf_id = ' . $field_id);
	}

	/*
	** Supprime un champ de profil
	** -----
	** $field_id ::		ID du champ a supprimer
	** $field_type ::	Constante définissant la table ciblée (PROFIL_FIELDS_CONTACT ou PROFIL_FIELDS_PERSONAL)
	*/
	public static function delete($field_id, $field_type)
	{
		switch (SQL_DBAL)
		{
			case 'mysql' :
			case 'mysqli' :
				$method = 'drop_column_mysql';
			break;

			case 'pgsql' :
				$method = 'drop_column_pgsql';
			break;

			case 'sqlite' :
				$method = 'drop_column_sqlite';
			break;
		}

		// Nom de la table
		switch ($field_type)
		{
			case PROFIL_FIELDS_CONTACT :
				$tablename =  'users_contact';
				$sql_field_name = 'contact_';
			break;
			
			case PROFIL_FIELDS_PERSONAL :
				$tablename = 'users_personal';
				$sql_field_name = 'personal_';
			break;
			
			default :
				trigger_error('Profil_fields->create() :: Mauvais paramètre pour le type de profil : '  . $field_type, FSB_ERROR);
			break;
		}
		self::$method(SQL_PREFIX . $tablename, $sql_field_name . $field_id);
		
		// On supprime le champ de la table profil_fields
		$sql = 'DELETE FROM ' . SQL_PREFIX . 'profil_fields
				WHERE pf_id = ' . $field_id;
		Fsb::$db->query($sql);
	}

	/*
	** Supprime une colone dans une table mysql
	** -----
	** $tablename ::		Nom de la table
	** $sql_field_name ::	Nom du champ
	*/
	private static function drop_column_mysql($tablename, $sql_field_name)
	{
		// On construit la requète ALTER
		$sql_alter = "ALTER TABLE $tablename DROP $sql_field_name";
		Fsb::$db->query($sql_alter);
	}

	/*
	** Supprime une colone dans une table PostgreSQL
	** -----
	** $tablename ::		Nom de la table
	** $sql_field_name ::	Nom du champ
	*/
	private static function drop_column_pgsql($tablename, $sql_field_name)
	{
		// On construit la requète ALTER
		$sql_alter = "ALTER TABLE $tablename DROP $sql_field_name";
		Fsb::$db->query($sql_alter);
	}

	/*
	** Supprime une colone dans une table SQLITE
	** -----
	** $tablename ::		Nom de la table
	** $sql_field_name ::	Nom du champ
	*/
	private static function drop_column_sqlite($tablename, $sql_field_name)
	{
		Fsb::$db->alter($tablename, 'DROP', $sql_field_name);
	}

	/*
	** Déplace un champ de profil
	** -----
	** $field_id ::	ID du champ
	** $field_move ::	1 pour déplacer vers le bas, -1 pour déplacer vers le haut
	** $field_type ::	Constante définissant la table ciblée (PROFIL_FIELDS_CONTACT ou PROFIL_FIELDS_PERSONAL)
	*/
	public static function move($field_id, $field_move, $field_type)
	{		
		$sql = 'SELECT pf_order
				FROM ' . SQL_PREFIX . 'profil_fields
				WHERE pf_id = ' . $field_id . '
					AND pf_type = ' . $field_type;
		$pf_order = Fsb::$db->get($sql, 'pf_order');
		
		$move = intval($pf_order) + $field_move;
		$sql = 'SELECT pf_order, pf_id
				FROM ' . SQL_PREFIX . 'profil_fields
				WHERE pf_order = ' . $move . '
					AND pf_type = ' . $field_type;
		$result = Fsb::$db->query($sql);
		$dest = Fsb::$db->row($result);
		Fsb::$db->free($result);
		
		if ($dest)
		{
			Fsb::$db->update('profil_fields', array(
				'pf_order' =>	$dest['pf_order'],
			), 'WHERE pf_id = ' . $field_id);
			
			Fsb::$db->update('profil_fields', array(
				'pf_order' =>	$pf_order,
			), 'WHERE pf_id = ' . $dest['pf_id']);
		}
	}
}

/* EOF */