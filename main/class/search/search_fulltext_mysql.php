<?php
/*
** +---------------------------------------------------+
** | Name :		~/main/class/search/search_fulltext_mysql.php
** | Begin :	12/07/2007
** | Last :		13/11/2007
** | User :		Genova
** | Project :	Fire-Soft-Board 2 - Copyright FSB group
** | License :	GPL v2.0
** +---------------------------------------------------+
*/

/*
** Méthode FULLTEXT de MySQL 4
**	+ Avantage : Rapide, facile à mettre en oeuvre
**	+ Inconvénient : Compatible uniquement MySQL >= 4, ne prend pas en compte les mots de moins de
**		4 lettres, ne prend pas en compte les mots contenu dans au moins 50% des résultats
*/
class Search_fulltext_mysql extends Search
{
	/*
	** CONSTRUCTEUR
	*/
	public function __construct()
	{
		$sql = 'SHOW VARIABLES LIKE \'ft_%\'';
		$result = Fsb::$db->query($sql);
		$rows = Fsb::$db->rows($result, 'assoc', 'Variable_name');
		$this->min_len = $rows['ft_min_word_len']['Value'];
		$this->max_len = $rows['ft_max_word_len']['Value'];
	}

	/*
	** Procédure de recherche
	** -----
	** $keywords_array ::		Tableau des mots clefs
	** $author_nickname ::		Nom de l'auteur
	** $forum_idx ::			Tableau des IDX de forums autorisés
	** $topic ::				ID d'un topic si on cherche uniquement dans celui ci
	** $date ::					Date (en nombre de secondes) pour la recherche de messages
	*/
	public function _search($keywords_array, $author_nickname, $forum_idx, $topic_id, $date)
	{
		// Mots clefs
		if ($this->search_link == 'and')
		{
			foreach ($keywords_array AS $key => $word)
			{
				$keywords_array[$key] = '+' . $word;
			}
		}

		$iterator = 0;
		$return = array();
		if ($this->search_in_post)
		{
			$select = new Sql_select();
			$select->join_table('FROM', 'posts', 'p_id');
			$select->where('f_id IN (' . implode(', ', $forum_idx) . ')');

			// Recherche de mots clefs
			if ($keywords_array)
			{
				$select->where('AND MATCH (p_text) AGAINST (\'' . implode(' ', $keywords_array) . '\' IN BOOLEAN MODE)');
			}

			// Recherche d'auteur
			if ($author_nickname)
			{
				$select->where('AND p_nickname = \'' . Fsb::$db->escape($author_nickname) . '\'');
			}

			if ($topic_id)
			{
				$select->where('AND t_id = ' . $topic_id);
			}

			if ($date > 0)
			{
				$select->where('AND p_time > ' . CURRENT_TIME . ' - ' . $date);
			}

			// Résultats
			$result = $select->execute();
			while ($row = Fsb::$db->row($result))
			{
				$return[$row['p_id']] = $iterator++;
			}
			Fsb::$db->free($result);
			unset($select);
		}

		// Recherche dans les titres
		if ($this->search_in_title && $keywords_array)
		{
			$sql_author = '';
			if ($author_nickname)
			{
				$sql_author = 'AND p.p_nickname = \'' . Fsb::$db->escape($author_nickname) . '\'';
			}

			$select = new Sql_select();
			$select->join_table('FROM', 'topics t');
			$select->join_table('INNER JOIN', 'posts p', 'p.p_id', 'ON t.t_id = p.t_id ' . $sql_author);
			$select->where('t.f_id IN (' . implode(', ', $forum_idx) . ')');
			if ($date > 0)
			{
				$select->where('AND p.p_time > ' . CURRENT_TIME . ' - ' . $date);
			}

			if ($topic_id)
			{
				$select->where('AND p.t_id = ' . $topic_id);
			}
			$select->where('AND MATCH (t.t_title) AGAINST (\'' . implode(' ', $keywords_array) . '\' IN BOOLEAN MODE)');

			// Résultats
			$result = $select->execute();
			while ($row = Fsb::$db->row($result))
			{
				$return[$row['p_id']] = $iterator++;
			}
			Fsb::$db->free($result);
			unset($select);
		}

		return (array_flip($return));
	}
}

/* EOF */