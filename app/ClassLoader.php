<?php
/**
 * This is the class loader for S3C3.
 *
 * Class loader based loosely on the Doctrine class loader.
 *
 * @author Sjan Evardsson <sjan@evardsson.com>
 * @link http://www.evardsson.com/
 * @copyright Sjan Evardsson 2013
 * @version 1.0
 * @package s3c3
 */
namespace s3c3;
use \s3c3\except\ClassNotFoundException;

/**
 * class \s3c3\ClassLoader
 * This classloader expects namespaces to match directory structures for all sub-
 * namespaces below those it knows. Example:
 * ClassLoader::addNamespace('s3c3', S3C3_APP . '/');
 * $foo = new \s3c3\foobar\util\FooUtil() would look for a file named FooUtil.php in
 * S3C3_APP . '/foobar/util/'
 *
 * @since version 1.0
 */
class ClassLoader
{
	/**
	 * @var separator - string namespace separator
	 * @access private
	 */
    private $separator = '\\';

	/**
	 * string directory path
	 * @var path
	 * @access private
	 */
	private $path = '.';

	/**
	 * string file extension
	 * @var extension
	 * @access private
	 */
	private $extension = '.php';

	/**
	 * string namespace
	 * @var ns
	 * @access private
	 */
	private $ns;

	/**
	 * Creates a ClassLoader
	 * @param string namespace (null for global)
	 * @param string path (default is .)
	 * @param string namespaceSeparator (default is \)
	 * @param string extension (default is .php)
	 */
	public function __construct($namespace, $path = '.',
		$namespaceSeparator = '\\', $extension = '.php')
	{
		$this->ns = $namespace;
		if ($path{strlen($path)-1} !== DIRECTORY_SEPARATOR) $path .= DIRECTORY_SEPARATOR;
		$this->path = $path;
		$this->separator = $namespaceSeparator;
		$this->extension = $extension;
	}

	/**
	 * Create a new ClassLoader and add it to the spl_autoload
	 * @param string namespace (null for global)
	 * @param string path (default is .)
	 * @param string namespaceSeparator (default is \)
	 * @param string extension (default is .php)
	 */
	public static function addNamespace($namespace, $path = '.',
		$namespaceSeparator = '\\', $extension = '.php')
	{
		$loader = new self($namespace, $path, $namespaceSeparator, $extension);
		$loader->register();
	}

	/**
	 * Create a new default ClassLoader and add it to the spl_autoload
	 * Creates with null namespace and with a path of ROOT_DIR
	 */
	public static function addDefaultNamespace()
	{
		$loader = new self(null, S3C3_ROOT);
		$loader->register();
	}

	/**
	 * Register this ClassLoader with the spl_autoload
	 */
	public function register()
	{
		spl_autoload_register(array($this, 'loadClass'));
	}

	/**
	 * Unregister this ClassLoader from the spl_autoload
	 */
	public function unregister()
	{
		spl_autoload_unregister(array($this, 'loadClass'));
	}

	/**
	 * Loads (or tries to load) a class.
	 * @param string classname
	 */
	public function loadClass($classname)
	{
		if (is_null($this->ns) && strpos($classname, $this->separator) !== false) {
			return false;
		}
		if ($this->ns !== null && strpos($classname, $this->ns.$this->separator) !== 0) {
			return false;
		}
		// tests for collections in root namespace with _ psuedo-namespacing
		if (is_null($this->ns) && (
		       substr($classname, 0, 5)  == 'ADODB'
		    || substr($classname, 0, 7)  == 'PHPUnit'
		    || substr($classname, 0, 4)  == 'PHP_'))
		{
			return false;
		}
		$bareclassname = str_replace($this->ns.$this->separator, '', $classname);
		$path_add = '';
		if (false !== strpos($bareclassname, $this->separator)) {
		    $parts = explode($this->separator, $bareclassname);
		    $bareclassname = array_pop($parts);
		    $path_add = implode(DIRECTORY_SEPARATOR, $parts);
		}
		$classfile = $this->path . $path_add . DIRECTORY_SEPARATOR . $bareclassname . $this->extension;
		$classfile = str_replace(DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $classfile);
		try {
			require $classfile;
		} catch (\Exception $e) {
		    $errmsg = sprintf('Unable to find class %1$s in directory %2$s', array($classname, $this->path));
			throw new ClassNotFoundException($errmsg, $e);
		}
		return true;
	}
}

