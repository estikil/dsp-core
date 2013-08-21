<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) (DSP)
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2013 DreamFactory Software, Inc. <developer-support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
use DreamFactory\Common\Enums\OutputFormats;
use DreamFactory\Platform\Enums\ResponseFormats;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Services\SystemManager;
use DreamFactory\Platform\Utility\ResourceStore;
use DreamFactory\Yii\Controllers\BaseWebController;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\OutputFormat;
use Kisma\Core\Utility\Curl;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Inflector;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use Kisma\Core\Utility\Sql;

/**
 * AdminController.php
 * The administrative site controller. This is the replacement for the javascript admin app. WIP
 */
class AdminController extends BaseWebController
{
	//*************************************************************************
	//* Members
	//*************************************************************************

	/**
	 * @var int
	 */
	protected $_format = OutputFormat::Raw;

	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * {@InheritDoc}
	 */
	public function init()
	{
		parent::init();

		$this->layout = 'admin';
		$this->defaultAction = 'index';

		$this->addUserActions( static::Authenticated, array( 'index', 'services', 'applications', 'authorizations' ) );
	}

	/**
	 * @param array $options
	 * @param bool  $fromCreate
	 *
	 * @throws DreamFactory\Platform\Exceptions\BadRequestException
	 */
	public function actionUpdate( $options = array(), $fromCreate = false )
	{
		$_resourceId = strtolower( trim( FilterInput::request( 'resource', null, FILTER_SANITIZE_STRING ) ) );
		$_id = FilterInput::request( 'id', null, FILTER_SANITIZE_STRING );

		if ( empty( $_resourceId ) || ( empty( $_resourceId ) && empty( $_id ) ) )
		{
			throw new BadRequestException( 'No resource and/or path specified.' );
		}

		//	Handle a plural request
		$_temp = $_resourceId[strlen( $_resourceId ) - 1];
		if ( 's' == $_temp && $_resourceId == Inflector::pluralize( substr( $_resourceId, 0, -1 ) ) )
		{
			$_resourceId = substr( $_resourceId, 0, -1 );
		}

		$_resource = ResourceStore::resource( $_resourceId );

		$this->render( 'update', array( 'resource' => $_resource->processRequest( $_resourceId . '/' . $_id, Option::server( 'REQUEST_METHOD' ) ) ) );
	}

	/**
	 *
	 */
	public function actionIndex()
	{
		static $_resourceColumns
		= array(
			'app'           => array(
				'header'   => 'Installed Applications',
				'resource' => 'app',
				'fields'   => array( 'id', 'api_name', 'url', 'is_active' ),
				'labels'   => array( 'ID', 'Name', 'Starting Path', 'Active' )
			),
			'app_group'     => array(
				'header'   => 'Application Groups',
				'resource' => 'app_group',
				'fields'   => array( 'id', 'name', 'description' ),
				'labels'   => array( 'ID', 'Name', 'Description' )
			),
			'user'          => array(
				'header'   => 'Users',
				'resource' => 'user',
				'fields'   => array( 'id', 'email', 'first_name', 'last_name', 'created_date' ),
				'labels'   => array( 'ID', 'Email', 'First Name', 'Last Name', 'Created' )
			),
			'role'          => array(
				'header'   => 'Roles',
				'resource' => 'role',
				'fields'   => array( 'id', 'name', 'description', 'is_active' ),
				'labels'   => array( 'ID', 'Name', 'Description', 'Active' )
			),
			'data'          => array(
				'header'   => 'Data',
				'resource' => 'db',
				'fields'   => array(),
				'labels'   => array(),
			),
			'service'       => array(
				'header'   => 'Services',
				'resource' => 'service',
				'fields'   => array( 'id', 'api_name', 'type_id', 'storage_type_id', 'is_active' ),
				'labels'   => array( 'ID', 'Endpoint', 'Type', 'Storage Type', 'Active' ),
			),
			'schema'        => array(
				'header'   => 'Schema Manager',
				'resource' => 'schema',
				'fields'   => array(),
				'labels'   => array(),
			),
			'config'        => array(
				'header'   => 'System Configuration',
				'resource' => 'config',
				'fields'   => array(),
				'labels'   => array(),
			),
			'provider'      => array(
				'header'   => 'Auth Providers',
				'resource' => 'provider',
				'fields'   => array( 'id', 'provider_name', 'api_name' ),
				'labels'   => array( 'ID', 'Name', 'Endpoint' ),
			),
			'provider_user' => array(
				'header'   => 'Provider Users',
				'resource' => 'provider_user',
				'fields'   => array( 'id', 'user_id', 'provider_id', 'provider_user_id', 'last_use_date' ),
				'labels'   => array( 'ID', 'User', 'Provider', 'Provider User ID', 'Last Used' ),
			),
		);

		foreach ( $_resourceColumns as $_resource => &$_config )
		{
			if ( !isset( $_config['columns'] ) )
			{
				if ( isset( $_config['fields'] ) )
				{
					$_config['columns'] = array();

					foreach ( $_config['fields'] as $_field )
					{
						$_config['columns'][] = array( 'sName' => $_field );
					}

					$_config['fields'] = implode( ',', $_config['fields'] );
				}
			}
		}

		$this->render( 'index', array( 'resourceColumns' => $_resourceColumns ) );
//		$this->render( 'index', array( 'resourceColumns' => Pii::getParam( 'admin.resource_schema', array() ) ) );
	}

	/**
	 *
	 */
	public function actionApplications()
	{
		if ( Pii::postRequest() )
		{
		}

		$this->render( 'applications' );
	}

	/**
	 *
	 */
	public function actionProviders()
	{
		if ( Pii::postRequest() )
		{
		}

		$this->render( 'Providers' );
	}

	/**
	 * @return string
	 */
	public function getFormat()
	{
		return $this->_format;
	}

	/**
	 * @param string $format
	 *
	 * @return RestController
	 */
	public function setFormat( $format )
	{
		$this->_format = $format;

		return $this;
	}
}
