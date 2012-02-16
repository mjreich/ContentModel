<?php
namespace ContentModel;
/**
 * ContentModel
 *
 * ContentModel — Creates a simple ORM style model on top of the types defined by the ContentManagerExtender.
 *
 * @package    ContentModel
 * @version    0.1.0
 * @author     Seabourne Consulting
 * @license    MIT License
 * @copyright  2012 Seabourne Consulting
 * @link       https://mjreich@github.com/mjreich/ContentModel.git
 */

use \Cumula\EventDispatcher as EventDispatcher;

/**
 * GenericContentModel Class
 *
 * The GenericContentModel implements a generic ORM style class handler for a particular ContentManager type.  This
 * enables you to interact with the Content Manager using the standard MVC style model, rather than the programmatic API.
 *
 * @package ContentModel
 * @author Mike Reich
 */
class GenericContentModel extends EventDispatcher {
	/**
	 * Internal hash to store the field values for the model
	 *
	 * @var array
	 */
	protected $_values;
	
	/**
	 * Public variable indicating the type, set at instantiation
	 *
	 * @var string
	 */
	public $type;
	
	/**
	 * Boolean flag indicating whether the model exists in the data store.
	 *
	 * @var boolean
	 */
	public $exists;
	
	/**
	 * Constructor.
	 *
	 * @param string $type	The ContentManager type of the model 
	 * @param boolean $exists 	A boolean flag that indicates whether the model has been saved
	 * @param array $values An array of key/value pairs used to instantiate the model at load
	 * @author Mike Reich
	 */
	public function __construct($type, $exists = false, $values = array()) {
		parent::__construct();
		$this->type = $type;
		$this->exists = $exists;
		$this->_values = (array)$values;
	}
	
	////////////////////////////////////////////////////////
	// Static Methods
	////////////////////////////////////////////////////////
	
	/**
	 * Static find method.  This method accepts an array of arguments compatible with the ContentManager::query function.
	 * The default query type is 'AND'.
	 *
	 * @param array $args an array of key/value pairs that are compatible with the ContentManager::query function.
	 * @param string $qtype either AND or OR, indicating how the results should be determined based on the $args.
	 * @param string $sort a field to sort by, should be present in the model. If not, sort is ignored.
	 * @param string $order either ASC or DESC, indicating the order by which to sort the $sort field
	 * @return array|boolean Returns an array of results, or false if the search failed.
	 * @author Mike Reich
	 */
	public static function find($args, $qtype = 'AND', $sort = null, $order = null) {
		if(is_string($args) || is_integer($args)) {
			return new static(static::getType(), true, I('ContentManager')->load($args));
		} else if(is_array($args)) {
			$args['type'] = static::getType();
			$r = I('ContentManager')->query($args, $qtype, $sort, $order);
			$return = array();
			foreach($r as $result) {
				$return[] = new GenericContentModel(static::getType(), true, $result);
			}
			return $return;
		}
		return false;
	}
	
	/**
	 * Static function for determining the type based on the instantiated class name.  The class name should equal the
	 * the ContentManager type for the object.
	 *
	 * @return string The ContentManager type of the class.
	 * @author Mike Reich
	 */
	public static function getType() {
		$type = get_called_class();
		$parts = explode('\\', $type);
		if(count($parts) > 1) {
			return $parts[count($parts)-1];
		} else {
			return $parts[0];
		}
	}
	
	/**
	 * Updates a bulk set of attributes for a model, determined by the passed id.
	 *
	 * @param string $id A UUID of the object to update.
	 * @param array $vals An array of key/value pairs for the fields and values to update.
	 * @return object|boolean The object or false if the update did not succeed.
	 * @author Mike Reich
	 */
	public static function updateAttributes($id, $vals) {
		return (I('ContentManager')->update($id, $vals));
	}
	
	/**
	 * Creates a new, unsaved and empty instance of the model.
	 *
	 * @return object A new instance.
	 * @author Mike Reich
	 */
	public static function getNew() {
		return new self(static::getType());
	}
	
	/**
	 * Creates and saves a new object.  Use getNew if you only want a new object without saving first.
	 *
	 * @param array $args An array of key/value pairs to initialize the new model
	 * @return object A new object.
	 * @author Mike Reich
	 */
	public static function create($args) {
		return new self(static::getType(), true, I('ContentManager')->create(static::getType(), $args));
	}
	
	/**
	 * Magic method for handling dynamic find queries.  This method handles methods with the following signatures:
	 * **findBy<fieldName>[AND|OR]<fieldName>**
	 * Only AND or OR queries are supported, not mixing the two in the same complex query.  Though you can have multiple ANDs
	 * or ORs in the same statement.
	 *
	 * @param string $function The incoming function name to parse
	 * @param array $vars The query arguments that correspond to each field name in the function signature.
	 * @return array|boolean Returns either an array if the query succeeded (though it may be empty if no results are found), or false.
	 * @author Mike Reich
	 */
	public static function __callStatic($function, $vars) {
		$parts = explode('By', $function);
		if(count($parts) > 1) {
			$parts[0] = str_replace('First', '', $parts[0]);
			$parts[0] = str_replace('All', '', $parts[0]);
			if($parts[0] == 'find') {
				$args = array();
				$andConditions = explode('And', $parts[1]);
				$orConditions = explode('Or', $parts[1]);
				$qtype = 'AND';
				if(count($andConditions) > 1) {
					foreach($andConditions as $key => $cond) {
						$args[lcfirst($cond)] = $vars[$key];
					}
				} else if(count($orConditions) > 1) {
					foreach($orConditions as $key => $cond) {
						$args[lcfirst($cond)] = $vars[$key];
					}
					$qtype = 'OR';
				} else {
					$args[lcfirst($parts[1])] = $vars[0];
				}
				$ret = static::find($args, $qtype);
				if(strstr($function, 'First')) 
					return empty($ret) ? false : $ret[0];
				else if(strstr($function, 'All'))
					return $ret;
				else
					return $ret;
			}
		}
	}
	
	////////////////////////////////////////////////////////
	// Instance Methods
	////////////////////////////////////////////////////////
	
	/**
	 * Magic method for stringifying the object.  Returns a JSON encoded array of the fields/values.
	 *
	 * @return string	A JSON encoded string array of the fields.
	 * @author Mike Reich
	 */
	public function __toString() {
		return json_encode($this->_values);
	}
	
	public function getValues() {
		return $this->_values;
	}
	
	/**
	 * Saves the current model.  If the model doesn't exist, it is created.
	 *
	 * @return object|boolean Returns the object if successful, false if unsuccessful.
	 * @author Mike Reich
	 */
	public function save() {
		if($this->exists)
			return \I('ContentManager')->update($this->uuid, $this->_values);
		else
			return \I('ContentManager')->create($this->type, $this->_values);
	}
	
	/**
	 * Deletes the current model from the ContentManager.
	 *
	 * @return boolean	Returns true if successful, false if not.
	 * @author Mike Reich
	 */
	public function delete() {
		if(!$this->exists)
			return false;
		if(\I('ContentManager')->delete($this->uuid)) {
			$this->exists = false;
			return true;
		}
	}
	
	/**
	 * Mass update the object with new values.
	 *
	 * @param array $vals An array of key/value pairs corresponding to the fields to update.
	 * @return object|boolean	Returns the object if successful, otherwise false.
	 * @author Mike Reich
	 */
	public function update($vals) {
		if($this->exists) {
			$this->_values = (array)static::updateAttributes($this->uuid, $vals);
			return $this;
		} else {
			return false;
		}
	}
	
	/**
	 * Magic method for handling field value acccess
	 *
	 * @param string $name The name of the field to get.
	 * @return void
	 * @author Mike Reich
	 */
	public function __get($name) {
		if(isset($this->_values[$name]))
			return $this->_values[$name];
	}
	
	/**
	 * Magic method for handling field value assignments
	 *
	 * @param string $name The name of the field to set
	 * @param string $value The value of the field to set.
	 * @return void
	 * @author Mike Reich
	 */
	public function __set($name, $value) {
		$this->_values[$name] = $value;
	}
	
	/**
	 * Magic method for determining field existence
	 *
	 * @param string $name The name of the field to determine if exists.
	 * @return void
	 * @author Mike Reich
	 */
	public function __isset($name) {
		return isset($this->_values[$name]);
	}
	
	/**
	 * Magic method for unsetting fields.
	 *
	 * @param string $name The name of the field to unset.
	 * @return void
	 * @author Mike Reich
	 */
	public function __unset($name) {
		unset($this->_values[$name]);
	}
	
}