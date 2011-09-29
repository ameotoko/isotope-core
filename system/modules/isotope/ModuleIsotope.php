<?php if (!defined('TL_ROOT')) die('You can not access this file directly!');

/**
 * TYPOlight Open Source CMS
 * Copyright (C) 2005-2010 Leo Feyer
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 * @copyright  Winans Creative 2009, Intelligent Spark 2010, iserv.ch GmbH 2010
 * @author     Fred Bliss <fred.bliss@intelligentspark.com>
 * @author     Andreas Schempp <andreas@schempp.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 */


abstract class ModuleIsotope extends Module
{

	/**
	 * Isotope object
	 * @var object
	 */
	protected $Isotope;

	/**
	 * Disable caching of the frontend page if this module is in use.
	 * Usefule to enable in a child classes.
	 * @var bool
	 */
	protected $blnDisableCache = false;


	public function __construct(Database_Result $objModule, $strColumn='main')
	{
		parent::__construct($objModule, $strColumn);

		if (TL_MODE == 'FE')
		{
			$this->import('Isotope');

			if (FE_USER_LOGGED_IN)
			{
				$this->import('FrontendUser', 'User');
			}

			// Load Isotope javascript and css
			$GLOBALS['TL_JAVASCRIPT'][] = 'system/modules/isotope/html/isotope.js';
			$GLOBALS['TL_CSS'][] = 'system/modules/isotope/html/isotope.css';

			// Disable caching for pages with certain modules (eg. Cart)
			if ($this->blnDisableCache)
			{
				global $objPage;
				$objPage->cache = 0;
			}

			// Make sure we have a reader page
			if (!$this->iso_reader_jumpTo)
			{
				global $objPage;
				$this->iso_reader_jumpTo = $objPage->id;
			}
		}
	}


	/**
	 * Include messages if enabled
	 * @return string
	 */
	public function generate()
	{
		$strBuffer = parent::generate();

		// Prepend any messages to the module output
		if ($this->iso_includeMessages)
		{
			$strBuffer = IsotopeFrontend::getIsotopeMessages() . $strBuffer;
		}

		return $strBuffer;
	}


	/**
	 * Shortcut for a single product by ID or from database result
	 *
	 * @deprecated
	 * @see IsotopeFrontend::getProduct()
	 */
	protected function getProduct($objProductData, $blnCheckAvailability=true)
	{
		trigger_error('Using ModuleIsotope::getProduct() is deprecated. Please use IsotopeFrontend::getProduct()', E_USER_NOTICE);
		return IsotopeFrontend::getProduct($objProductData, $this->iso_reader_jumpTo, $blnCheckAvailability);
	}


	/**
	 * Shortcut for a single product by alias (from url?)
	 *
	 * @deprecated
	 * @see IsotopeFrontend::getProducts()
	 */
	protected function getProductByAlias($strAlias, $blnCheckAvailability=true)
	{
		trigger_error('Using ModuleIsotope::getProductByAlias() is deprecated. Please use IsotopeFrontend::getProductByAlias()', E_USER_NOTICE);
		return IsotopeFrontend::getProductByAlias($strAlias, $this->iso_reader_jumpTo, $blnCheckAvailability);
	}


	/**
	 * Generate products from database result or array of IDs.
	 *
	 * @deprecated
	 * @see IsotopeFrontend::getProducts()
	 */
	protected function getProducts($objProductData, $blnCheckAvailability=true, array $arrFilters=array(), array $arrSorting=array())
	{
		trigger_error('Using ModuleIsotope::getProducts() is deprecated. Please use IsotopeFrontend::getProducts()', E_USER_NOTICE);
		return IsotopeFrontend::getProducts($objProductData, $this->iso_reader_jumpTo, $blnCheckAvailability, $arrFilters, $arrSorting);
	}


	/**
	 * The ids of all pages we take care of. This is what should later be used eg. for filter data.
	 */
	protected function findCategoryProducts($strCategoryScope, $strWhere='')
	{
		if ($this->defineRoot && $this->rootPage > 0)
		{
			$objPage = $this->getPageDetails($this->rootPage);
		}
		else
		{
			global $objPage;
		}

		switch($strCategoryScope)
		{
			case 'global':
				$arrCategories = $this->getChildRecords($objPage->rootId, 'tl_page');
				$arrCategories[] = $objPage->rootId;
				break;

			case 'current_and_first_child':
				$arrCategories = $this->Database->execute("SELECT id FROM tl_page WHERE pid={$objPage->id}")->fetchEach('id');
				$arrCategories[] = $objPage->id;
				break;

			case 'current_and_all_children':
				$arrCategories = $this->getChildRecords($objPage->id, 'tl_page');
				$arrCategories[] = $objPage->id;
				break;

			case 'parent':
				$arrCategories = array($objPage->pid);
				break;

			case 'product':
				$objProduct = IsotopeFrontend::getProductByAlias($this->Input->get('product'));

				if ($objProduct instanceof IsotopeProduct)
				{
					$arrCategories = $objProduct->categories;
				}
				else
				{
					return array(0);
				}
				break;

			case 'current_category':
			default:
				$arrCategories = array($objPage->id);
				break;
		}

		$arrIds = $this->Database->execute("SELECT pid FROM tl_iso_product_categories WHERE page_id IN (" . implode(',', $arrCategories) . ")" . ($strWhere != '' ? ' AND '.$strWhere : ''))->fetchEach('pid');

		return count($arrIds) ? $arrIds : array(0);
	}


	/**
	 * Generate the URL from existing $_GET parameters.
	 * Use $this->Input->setGet('var', null) to remove a parameter from the final URL.
	 */
	protected function generateRequestUrl()
	{
		if (!strlen($this->Environment->request))
		{
			return '';
		}

		$strRequest = preg_replace('/\?.*$/i', '', $this->Environment->request);
		$strRequest = preg_replace('/' . preg_quote($GLOBALS['TL_CONFIG']['urlSuffix'], '/') . '$/i', '', $strRequest);

		$arrFragments = explode('/', $strRequest);

		// Skip index.php
		if (strtolower($arrFragments[0]) == 'index.php')
		{
			array_shift($arrFragments);
		}

		// HOOK: add custom logic
		if (isset($GLOBALS['TL_HOOKS']['getPageIdFromUrl']) && is_array($GLOBALS['TL_HOOKS']['getPageIdFromUrl']))
		{
			foreach ($GLOBALS['TL_HOOKS']['getPageIdFromUrl'] as $callback)
			{
				$this->import($callback[0]);
				$arrFragments = $this->$callback[0]->$callback[1]($arrFragments);
			}
		}

		$strParams = '';
		$arrGet = array();

		// Add fragments to URL params
		for ($i=1; $i<count($arrFragments); $i+=2)
		{
			if (isset($_GET[$arrFragments[$i]]))
			{
				$key = urldecode($arrFragments[$i]);

				$this->Input->setGet($key, null);
				$strParams .= '/' . $key . '/' . urldecode($arrFragments[$i+1]);
			}
		}

		// Add get parameters to URL
		if (is_array($_GET) && count($_GET))
		{
			foreach( $_GET as $key => $value )
			{
				$arrGet[] = $key . '=' . $value;
			}
		}

		global $objPage;

		return $this->generateFrontendUrl($objPage->row(), $strParams) . (count($arrGet) ? ('?'.implode('&', $arrGet)) : '');
	}
}

