<?php
/*
** +---------------------------------------------------+
** | Name :			~/main/forum/forum_download.php
** | Begin :		03/01/2006
** | Last :			11/12/2007
** | User :			Genova
** | Project :		Fire-Soft-Board 2 - Copyright FSB group
** | License :		GPL v2.0
** +---------------------------------------------------+
*/

/*
** Téléchargement d'un fichier joint.
** Les fichiers sont securises par un .HTACCESS, il est necessaire de passer par cette page
** pour pouvoir les afficher. Donc les liens directs ne marchent pas (sauf si vous decidez de
** supprimer le .HTACCESS du dossier upload).
**
** Il est possible de limiter le telechargement des fichiers a certaines IP, en verifiant le REFERER
** du client. Pour cela il faut passer la propriete $this->check_referer sur TRUE, et il faut ajout les
** listes d'IP dans la proriete $this->ip. Par exemple :
**		public $ip_allow = array('127.0.0.1', '127.0.0.*', etc...);
** On peut aussi interdire des IP, par exemple :
**		public $ip_disallow = array('127.0.0.1', etc...);
** A noter que si vous souhaitez activer la verification par referer, vous DEVEZ fournir une liste
** d'IP autorisees. Si vous souhaitez passer outre cette liste d'IP autorisee (et donc donner acces a toutes
** les IP), laissez la propriete $this->ip_allow vide.
*/
class Fsb_frame_child extends Fsb_frame
{
	// Paramètres d'affichage de la page (barre de navigation, boite de stats)
	public $_show_page_header_nav = FALSE;
	public $_show_page_footer_nav = FALSE;
	public $_show_page_stats = FALSE;

	// A mettre sur TRUE pour la verification des IP
	public $check_referer = FALSE;

	// Liste des IP autorisees
	public $ip_allow = array('127.0.0.1');

	// Liste des IP interdites
	public $ip_disallow = array();

	/*
	** Constructeur
	*/
	public function main()
	{
		$id = intval(Http::request('id'));

		// Verification des IP ?
		if ($this->check_referer)
		{
			$this->check_ip();
		}

		// On récupère les données du fichier dans la base de donnée
		$sql = 'SELECT upload_mimetype, upload_filename, upload_realname, upload_auth, upload_time
				FROM ' . SQL_PREFIX . 'upload
				WHERE upload_id = ' . $id;
		$row = Fsb::$db->request($sql);

		if (!$row || !file_exists(ROOT . 'upload/' . $row['upload_filename']))
		{
			Display::message('download_not_exists');
		}

		// Droit de téléchargement ?
		if (Fsb::$session->auth() < $row['upload_auth'])
		{
			Display::message('not_allowed_to_download');
		}

		// Mise à jour du compteur de téléchargement si ce n'est pas une image
		if (!isset($_GET['nocount']))
		{
			Fsb::$db->update('upload', array(
				'upload_total' =>	array('upload_total + 1', 'is_field' => TRUE),
			), 'WHERE upload_id = ' . $id);

			// S'il s'agit d'une image, on force son téléchargement. Pour des raisons de compatibilité
			// avec les anciennes version, on se base sur un timestamp.
			if (strpos($row['upload_mimetype'], 'image/') !== FALSE && $row['upload_time'] > 1197386270)
			{
				$row['upload_mimetype'] = 'application/octetstream';
			}
		}

		// On impose le type mime application/octetstream pour les fichiers qui ne sont pas des images
		if (strpos($row['upload_mimetype'], 'image/') === FALSE || $row['upload_mimetype'] == 'application/octet-stream')
		{
			$row['upload_mimetype'] = 'application/octetstream';
		}

		// Téléchargement
		Http::download($row['upload_realname'], file_get_contents(ROOT . 'upload/' . $row['upload_filename']), $row['upload_mimetype']);
	}

	/*
	** Si la verification de referer a ete activee on verifie les IP autorisees et interdites
	*/
	public function check_ip()
	{
		$referer = (isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER']) ? @parse_url($_SERVER['HTTP_REFERER']) : NULL;
		if (!$referer)
		{
			Display::message('not_allowed_to_download');
		}

		// On liste les IP de l'hote
		if ($list_ip = gethostbynamel($referer['host']))
		{
			// On verifie si l'IP est interdite
			foreach ($list_ip AS $ip)
			{
				if ($ip)
				{
					foreach ($this->ip_disallow AS $disallowed_ip)
					{
						if (String::is_matching($disallowed_ip, $ip))
						{
							Display::message('not_allowed_to_download');
						}
					}
				}
			}

			// On verifie si l'IP est autorisee
			if ($this->ip_allow)
			{
				$allow = FALSE;
				foreach ($list_ip AS $ip)
				{
					if ($ip)
					{
						foreach ($this->ip_allow AS $allowed_ip)
						{
							if (String::is_matching($allowed_ip, $ip))
							{
								$allow = TRUE;
								break 2;
							}
						}
					}
				}

				if (!$allow)
				{
					Display::message('not_allowed_to_download');
				}
			}
		}
	}
}

/* EOF */
