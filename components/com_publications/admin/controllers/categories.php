<?php
/**
 * @package    hubzero-cms
 * @copyright  Copyright 2005-2019 HUBzero Foundation, LLC.
 * @license    http://opensource.org/licenses/MIT MIT
 */
/**
 * Modified by CANARIE Inc. for the HSSCommons project.
 *
 * Summary of changes: Minor customization.
 */
 
namespace Components\Publications\Admin\Controllers;

use Hubzero\Component\AdminController;
use Components\Publications\Models\Orm\Category;
use Components\Publications\Models\Elements;
use Components\Publications\Tables\MasterType;
use stdClass;
use Request;
use Notify;
use Route;
use Lang;
use App;

require_once dirname(dirname(__DIR__)) . DS . 'models' . DS . 'orm' . DS . 'category.php';

/**
 * Manage publication categories
 */
class Categories extends AdminController
{
	/**
	 * Executes a task
	 *
	 * @return  void
	 */
	public function execute()
	{
		$this->registerTask('add', 'edit');
		$this->registerTask('apply', 'save');

		parent::execute();
	}

	/**
	 * List entries
	 *
	 * @return  void
	 */
	public function displayTask()
	{
		// Incoming
		$filters = array(
			'limit' => Request::getState(
				$this->_option . '.categories.limit',
				'limit',
				Config::get('list_limit'),
				'int'
			),
			'start' => Request::getState(
				$this->_option . '.categories.limitstart',
				'limitstart',
				0,
				'int'
			),
			'search' => Request::getState(
				$this->_option . '.categories.search',
				'search',
				''
			),
			'sort' => Request::getState(
				$this->_option . '.categories.sort',
				'filter_order',
				'id'
			),
			'sort_Dir' => Request::getState(
				$this->_option . '.categories.sortdir',
				'filter_order_Dir',
				'ASC'
			)
		);

		$filters['state'] = 'all';

		// Instantiate an object
		$entries = Category::all();

		if ($filters['search'])
		{
			$entries->whereLike('name', strtolower((string)$filters['search']), 1)
				->orWhereLike('description', strtolower((string)$filters['search']), 1)
				->resetDepth();
		}

		// Get records
		$rows = $entries
			->order($filters['sort'], $filters['sort_Dir'])
			->paginated('limitstart', 'limit')
			->rows();

		// Output the HTML
		$this->view
			->set('filters', $filters)
			->set('rows', $rows)
			->display();
	}

	/**
	 * Edit a type
	 *
	 * @param   object  $row
	 * @return  void
	 */
	public function editTask($row=null)
	{
		if (!User::authorise('core.edit', $this->_option)
		 && !User::authorise('core.create', $this->_option))
		{
			App::abort(403, Lang::txt('JERROR_ALERTNOAUTHOR'));
		}

		Request::setVar('hidemainmenu', 1);

		if (!is_object($row))
		{
			// Incoming (expecting an array)
			$id = Request::getArray('id', array(0));
			$id = is_array($id) ? $id[0] : $id;

			// Load the object
			$row = Category::oneOrNew($id);
		}

		// Get all contributable master types
		$objMT = new MasterType($this->database);
		$types = $objMT->getTypes('alias', 1);

		// Output the HTML
		$this->view
			->set('row', $row)
			->set('config', $this->config)
			->set('types', $types)
			->setLayout('edit')
			->display();
	}

	/**
	 * Save a type
	 *
	 * @return  void
	 */
	public function saveTask()
	{
		// Check for request forgeries
		Request::checkToken();

		if (!User::authorise('core.edit', $this->_option)
		 && !User::authorise('core.create', $this->_option))
		{
			App::abort(403, Lang::txt('JERROR_ALERTNOAUTHOR'));
		}

		$prop = Request::getArray('prop', array(), 'post');

		// Initiate extended database class
		$row = Category::oneOrNew($prop['id'])->set($prop);

		// Get the custom fields
		$fields = Request::getArray('fields', array(), 'post');
		if (is_array($fields))
		{
			$elements = new stdClass();
			$elements->fields = array();

			foreach ($fields as $val)
			{
				if ($val['title'])
				{
					$element = new stdClass();
					$element->default  = (isset($val['default'])) ? $val['default'] : '';
					$element->name     = (isset($val['name']) && trim($val['name']) != '') ? $val['name'] : strtolower(preg_replace("/[^a-zA-Z0-9]/", '', trim($val['title'])));
					$element->label    = $val['title'];
					$element->type     = (isset($val['type']) && trim($val['type']) != '') ? $val['type'] : 'text';
					$element->required = (isset($val['required'])) ? $val['required'] : '0';
					foreach ($val as $key => $v)
					{
						if (!in_array($key, array('default', 'type', 'title', 'name', 'required', 'options')))
						{
							$element->$key = $v;
						}
					}
					if (isset($val['options']))
					{
						$element->options = array();
						foreach ($val['options'] as $option)
						{
							if (trim($option['label']))
							{
								$opt = new stdClass();
								$opt->label = $option['label'];
								$opt->value = $option['label'];
								$element->options[] = $opt;
							}
						}
					}
					$elements->fields[] = $element;
				}
			}

			include_once dirname(dirname(__DIR__)) . DS . 'models' . DS . 'elements.php';

			$re = new Elements($elements);
			$row->set('customFields', $re->toString());
		}

		// Get parameters
		$params = Request::getArray('params', array(), 'post');

		$p = $row->params;

		if (is_array($params))
		{
			foreach ($params as $k => $v)
			{
				$p->set($k, $v);
			}
			$row->set('params', $p->toString());
		}

		// Store new content
		if (!$row->save())
		{
			Notify::error($row->getError());
			return $this->editTask($row);
		}

		Notify::success(Lang::txt('COM_PUBLICATIONS_CATEGORY_SAVED'));

		// Redirect to edit view?
		if ($this->getTask() == 'apply')
		{
			return $this->editTask($row);
		}

		$this->cancelTask();
	}

	/**
	 * Change status
	 * Redirects to list
	 *
	 * @return  void
	 */
	public function changestatusTask()
	{
		// Check for request forgeries
		Request::checkToken();

		if (!User::authorise('core.edit.state', $this->_option))
		{
			App::abort(403, Lang::txt('JERROR_ALERTNOAUTHOR'));
		}

		// Incoming
		$ids = Request::getArray('id', array(0));

		$success = 0;

		foreach ($ids as $id)
		{
			// Load row
			$row = Category::oneOrFail((int) $id);
			$row->set('state', $row->get('state') == 1 ? 0 : 1);

			// Save
			// Modified by CANARIE Inc. Beginning
			// replaced "if (!$row->store())"
			if (!$row->save())
			// Modified by CANARIE Inc. End
			{
				Notify::error($row->getError());
				continue;
			}

			$success++;
		}

		if ($success)
		{
			Notify::success(Lang::txt('COM_PUBLICATIONS_CATEGORY_ITEM_STATUS_CHNAGED'));
		}

		// Redirect
		$this->cancelTask();
	}

	/**
	 * Retrieve an element's options (typically called via AJAX)
	 *
	 * @return  void
	 */
	public function elementTask()
	{
		$ctrl = Request::getString('ctrl', 'fields');

		$option = new stdClass;
		$option->label = '';
		$option->value = '';

		$field = new stdClass;
		$field->label       = Request::getString('name', 0);
		$field->element     = '';
		$field->description = '';
		$field->text        = $field->label;
		$field->name        = $field->label;
		$field->default     = '';
		$field->type        = Request::getString('type', '');
		$field->options     = array(
			$option,
			$option
		);

		include_once dirname(dirname(__DIR__)) . DS . 'models' . DS . 'elements.php';

		$elements = new Elements();
		echo $elements->getElementOptions($field->name, $field, $ctrl);
	}

	/**
	 * Removes one or more entries
	 *
	 * @return  void
	 */
	public function removeTask()
	{
		// Check for request forgeries
		Request::checkToken();

		if (!User::authorise('core.delete', $this->_option))
		{
			App::abort(403, Lang::txt('JERROR_ALERTNOAUTHOR'));
		}

		// Incoming
		$ids = Request::getArray('id', array());
		$ids = (!is_array($ids) ? array($ids) : $ids);

		// Do we have any IDs?
		$success = 0;

		// Loop through each ID and delete the necessary items
		foreach ($ids as $id)
		{
			// Remove the profile
			$row = Category::oneOrFail($id);

			// Modified by CANARIE Inc. Beginning
			// replaced "if (!$row->delete())"
			if (!$row->destroy())
			// Modified by CANARIE Inc. End
			{
				Notify::error($row->getError());
				continue;
			}

			$success++;
		}

		if ($success)
		{
			Notify::success(Lang::txt('COM_PUBLICATIONS_CATEGORY_REMOVED'));
		}

		// Output messsage and redirect
		$this->cancelTask();
	}
}
