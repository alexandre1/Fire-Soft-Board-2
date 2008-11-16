<?php
/**
 * Fire-Soft-Board version 2
 * 
 * @package FSB2
 * @author Genova <genova@fire-soft-board.com>
 * @version $Id$
 * @license http://opensource.org/licenses/gpl-2.0.php GNU GPL 2
 */

/**
 * Generation et gestion d'un arbre de donnees
 */
class Tree extends Fsb_model
{
	/**
	 * Pointe sur l'element parent de l'arbre
	 *
	 * @var Tree_node
	 */
	public $document = null;
	
	/**
	 * Contient les differentes branches de l'arbre
	 *
	 * @var array
	 */
	protected $data = array();

	/**
	 * Ajoute un element a l'arbre
	 *
	 * @param int $id ID de l'element
	 * @param int $parent ID du parent de l'element
	 * @param mixed $data Informations sur l'element
	 */
	public function add_item($id, $parent, $data = null)
	{
		if (!isset($this->data[$id]))
		{
			$this->data[$id] = new Tree_node($data);
			$this->data[$id]->id = $id;
		}
		else 
		{
			$this->data[$id]->data = $data;
		}

		if (!isset($this->data[$parent]))
		{
			$this->data[$parent] = new Tree_node(array());
			$this->data[$parent]->id = $parent;
		}
		
		$this->data[$parent]->children[$id] = &$this->data[$id];
		$this->data[$id]->parent = &$this->data[$parent];
		
		$this->data[$id]->parents = $this->data[$id]->getParents();
		
		if ($this->document === null)
		{
			$this->document = &$this->data[$parent];
		}
	}
	
	/**
	 * Ecrase les informations de l'element par des nouvelles
	 *
	 * @param int $id ID de l'element
	 * @param mixed $data Nouvelles informations
	 */
	public function update_item($id, $data = null)
	{
		$this->data[$id]->data = $data;
	}
	
	/**
	 * Retourne un element dont l'ID est connue
	 *
	 * @param int $id ID de l'element
	 * @return Tree_node
	 */
	public function getByID($id)
	{
		return (isset($this->data[$id]) ? $this->data[$id] : null);
	}
	
	/**
	 * Affiche une representation de l'arbre, pour le debug
	 *
	 * @param Tree_node $node
	 * @param int $level
	 */
	public function debug($node = null, $level = 0)
	{
		if ($node === null)
		{
			$node = $this->document;
		}
		
		echo str_repeat('---', $level) . ' [' . $node->id . ']<br />';
		foreach ($node->children AS $child)
		{
			$this->debug($child, $level + 1);
		}
	}
}

/**
 * Feuille de l'arbre
 */
class Tree_node extends Fsb_model
{
	/**
	 * Informations sur la feuille
	 *
	 * @var mixed
	 */
	public $data;
	
	/**
	 * ID de la feuille
	 *
	 * @var int
	 */
	public $id;
	
	/**
	 * Liste des enfants
	 *
	 * @var array
	 */
	public $children = array();
	
	/**
	 * Liste d'ID des parents
	 *
	 * @var array
	 */
	public $parents = array();
	
	/**
	 * Pointe sur le parent
	 *
	 * @var Tree_node
	 */
	public $parent;

	/**
	 * Constructeur, assigne les informations a la feuille
	 *
	 * @param mixed $data
	 */
	public function __construct($data = null)
	{
		$this->data = $data;
	}
	
	/**
	 * Calcul les parents de la feuille
	 *
	 * @return array
	 */
	public function getParents()
	{
		$parents = array();
		if ($this->parent)
		{
			$p = $this->parent;
			while (true)
			{
				$parents[] = $p->id;
				if (!$p->parent)
				{
					break;
				}
				$p = $p->parent;
			}
		}

		return ($parents);
	}
}

/* EOF */