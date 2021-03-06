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
use Components\Publications\Models\Orm\License;
use Request;
use Notify;
use Route;
use Lang;
use App;

require_once dirname(dirname(__DIR__)) . DS . 'models' . DS . 'orm' . DS . 'license.php';

/**
 * Manage publication licenses
 */
class Licenses extends AdminController
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
	 * List resource types
	 *
	 * @return  void
	 */
	public function displayTask()
	{
		// Incoming
		$filters = array(
			'limit' => Request::getState(
				$this->_option . '.licenses.limit',
				'limit',
				Config::get('list_limit'),
				'int'
			),
			'start' => Request::getState(
				$this->_option . '.licenses.limitstart',
				'limitstart',
				0,
				'int'
			),
			'search' => Request::getState(
				$this->_option . '.licenses.search',
				'search',
				''
			),
			'sort' => Request::getState(
				$this->_option . '.licenses.sort',
				'filter_order',
				'title'
			),
			'sort_Dir' => Request::getState(
				$this->_option . '.licenses.sortdir',
				'filter_order_Dir',
				'ASC'
			)
		);

		// Instantiate an object
		$entries = License::all();

		if ($filters['search'])
		{
			$entries->whereLike('title', strtolower((string)$filters['search']));
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
			$row = License::oneOrNew($id);
		}

		// Output the HTML
		$this->view
			->set('row', $row)
			->setLayout('edit')
			->display();
	}

	/**
	 * Save record
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

		$fields = Request::getArray('fields', array(), 'post');
		$fields = array_map('trim', $fields);

		// Initiate extended database class
		$row = License::oneOrNew($fields['id'])->set($fields);

		// Store new content
		if (!$row->save())
		{
			Notify::error($row->getError());
			return $this->editTask($row);
		}

		Notify::success(Lang::txt('COM_PUBLICATIONS_SUCCESS_LICENSE_SAVED'));

		// Redirect to edit view?
		if ($this->getTask() == 'apply')
		{
			return $this->editTask($row);
		}

		$this->cancelTask();
	}

	/**
	 * Reorder up
	 *
	 * @return  void
	 */
	public function orderupTask()
	{
		$this->reorderTask(-1);
	}

	/**
	 * Reorder down
	 *
	 * @return  void
	 */
	public function orderdownTask()
	{
		$this->reorderTask(1);
	}

	/**
	 * Reorders licenses
	 * Redirects to license listing
	 *
	 * @param   integer  $dir
	 * @return  void
	 */
	public function reorderTask($dir = 0)
	{
		// Check for request forgeries
		Request::checkToken();

		// Incoming
		$id = Request::getArray('id', array(0));

		// Load row
		$row = License::oneOrFail((int) $id[0]);

		// Update order
		if (!$row->move($dir))
		{
			Notify::error($row->getError());
		}

		$this->cancelTask();
	}

	/**
	 * Makes one license default
	 * Redirects to license listing
	 *
	 * @return  void
	 */
	public function makedefaultTask()
	{
		// Check for request forgeries
		Request::checkToken();

		if (!User::authorise('core.edit.state', $this->_option))
		{
			App::abort(403, Lang::txt('JERROR_ALERTNOAUTHOR'));
		}

		// Incoming
		$id = Request::getArray('id', array(0));

		if (count($id) > 1)
		{
			Notify::warning(Lang::txt('COM_PUBLICATIONS_LICENSE_SELECT_ONE'));
			return $this->cancelTask();
		}

		// Load row
		$row = License::oneOrFail((int) $id[0]);

		// Save
		if (!$row->setMain())
		{
			Notify::error($row->getError());
			return $this->cancelTask();
		}

		// Redirect
		Notify::success(Lang::txt('COM_PUBLICATIONS_SUCCESS_LICENSE_MADE_DEFAULT'));

		$this->cancelTask();
	}

	/**
	 * Change license status
	 * Redirects to license listing
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
			$row = License::oneOrFail((int) $id);
			$row->set('active', $row->get('active') == 1 ? 0 : 1);

			// Save
			if (!$row->save())
			{
				Notify::error($row->getError());
				continue;
			}

			$success++;
		}

		if ($success)
		{
			Notify::success(Lang::txt('COM_PUBLICATIONS_SUCCESS_LICENSE_PUBLISHED'));
		}

		// Redirect
		$this->cancelTask();
	}

	/**
	 * Remove one or more entries
	 *
	 * @return  void  Redirects back to main listing
	 */
	public function removeTask()
	{
		// Check for request forgeries
		Request::checkToken();

		if (!User::authorise('core.delete', $this->_option))
		{
			App::abort(403, Lang::txt('JERROR_ALERTNOAUTHOR'));
		}

		// Incoming (expecting an array)
		$ids = Request::getArray('id', array());
		$ids = (!is_array($ids) ? array($ids) : $ids);

		$success = 0;

		foreach ($ids as $id)
		{
			$row = License::oneOrFail($id);

			if ($row->isUsed())
			{
				Notify::error(Lang::txt('COM_PUBLICATIONS_ITEM_BEING_USED'));
				continue;
			}

			if (!$row->destroy())
			{
				Notify::error($row->getError());
				continue;
			}

			$success++;
		}

		if ($success)
		{
			// Modified by CANARIE Inc. Beginning
			// removed the nonexistent "$i" variable
			Notify::success(Lang::txt('COM_PUBLICATIONS_ITEMS_REMOVED'));
			// Modified by CANARIE Inc. End
		}

		// Redirect
		$this->cancelTask();
	}
}
