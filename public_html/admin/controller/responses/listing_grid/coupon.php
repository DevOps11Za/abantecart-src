<?php
/*------------------------------------------------------------------------------
  $Id$

  AbanteCart, Ideal OpenSource Ecommerce Solution
  http://www.AbanteCart.com

  Copyright © 2011 Belavier Commerce LLC

  This source file is subject to Open Software License (OSL 3.0)
  License details is bundled with this package in the file LICENSE.txt.
  It is also available at this URL:
  <http://www.opensource.org/licenses/OSL-3.0>

 UPGRADE NOTE:
   Do not edit or add to this file if you wish to upgrade AbanteCart to newer
   versions in the future. If you wish to customize AbanteCart for your
   needs please refer to http://www.AbanteCart.com for more information.
------------------------------------------------------------------------------*/
if (! defined ( 'DIR_CORE' ) || !IS_ADMIN) {
	header ( 'Location: static_pages/' );
}
class ControllerResponsesListingGridCoupon extends AController {
	private $error = array();

    public function main() {

	    //init controller data
        $this->extensions->hk_InitData($this,__FUNCTION__);

        $this->loadLanguage('sale/coupon');
		$this->loadModel('sale/coupon');

	    $page = $this->request->post['page']; // get the requested page
		$limit = $this->request->post['rows']; // get how many rows we want to have into the grid
		$sidx = $this->request->post['sidx']; // get index row - i.e. user click to sort
		$sord = $this->request->post['sord']; // get the direction

	    $data = array(
			'sort'  => $sidx,
			'order' => $sord,
			'start' => ($page - 1) * $limit,
			'limit' => $limit,
		    'content_language_id' => $this->session->data['content_language_id'],
		);
	    if ( isset($this->request->get['status']) ) {
		    $data['status'] = $this->request->get['status'];
	    }

	    $total = $this->model_sale_coupon->getTotalCoupons($data);
		if( $total > 0 ) {
			$total_pages = ceil($total/$limit);
		} else {
			$total_pages = 0;
		}

	    $response = new stdClass();
		$response->page = $page;
		$response->total = $total_pages;
		$response->records = $total;

	    $results = $this->model_sale_coupon->getCoupons($data);
	    $i = 0;
		foreach ($results as $result) {

            $response->rows[$i]['id'] = $result['coupon_id'];
			$response->rows[$i]['cell'] = array(
				$result['name'],
				$result['code'],
				$result['discount'],
				date($this->language->get('date_format_short'), strtotime($result['date_start'])),
				date($this->language->get('date_format_short'), strtotime($result['date_end'])),
				$this->html->buildCheckbox(array(
                    'name'  => 'status['.$result['coupon_id'].']',
                    'value' => $result['status'],
                    'style'  => 'btn_switch',
                )),
			);
			$i++;
		}

		//update controller data
        $this->extensions->hk_UpdateData($this,__FUNCTION__);

		$this->load->library('json');
		$this->response->setOutput(AJson::encode($response));
	}

	public function update() {

		//init controller data
        $this->extensions->hk_InitData($this,__FUNCTION__);

	    $this->loadModel('sale/coupon');
        $this->loadLanguage('sale/coupon');
        if (!$this->user->canModify('sale/coupon')) {
			$this->response->setOutput( sprintf($this->language->get('error_permission_modify'), 'sale/coupon') );
            return;
		}

		switch ($this->request->post['oper']) {
			case 'del':
				$ids = explode(',', $this->request->post['id']);
				if ( !empty($ids) )
				foreach( $ids as $id ) {
					$this->model_sale_coupon->deleteCoupon($id);
				}
				break;
			case 'save':
				$ids = explode(',', $this->request->post['id']);
				if ( !empty($ids) )
				foreach( $ids as $id ) {
					$s = isset($this->request->post['status'][$id]) ? $this->request->post['status'][$id] : 0;
					$this->model_sale_coupon->editCoupon($id, array('status' => $s) );
				}
				break;

			default:
				//print_r($this->request->post);

		}

		//update controller data
        $this->extensions->hk_UpdateData($this,__FUNCTION__);
	}

    /**
     * update only one field
     *
     * @return void
     */
    public function update_field() {

		//init controller data
        $this->extensions->hk_InitData($this,__FUNCTION__);

        $this->loadLanguage('sale/coupon');
		$this->loadModel('sale/coupon');

        if (!$this->user->canModify('sale/coupon')) {
			$this->response->setOutput( sprintf($this->language->get('error_permission_modify'), 'sale/coupon') );
            return;
		}

	    if ( isset( $this->request->get['id'] ) ) {
		    foreach( $this->request->post as $field => $value ) {
			    if (($field == 'uses_total' && $value == '') || ($field == 'uses_customer' && $value == '')) {
					$value = -1;
				}

		        $err = $this->_validateForm($field, $value );
			    if ( !$err ) {
			        $this->model_sale_coupon->editCoupon($this->request->get['id'], array( $field => $value) );
			    } else {
					$dd = new ADispatcher('responses/error/ajaxerror/validation',array('error_text'=>$err));
					return $dd->dispatch();
			    }
		    }
		    return;
	    }

	    //request sent from jGrid. ID is key of array
        foreach ($this->request->post as $field => $value ) {
            foreach ( $value as $k => $v ) {
                $err = $this->_validateForm($field, $v );
			    if ( !$err ) {
			        $this->model_sale_coupon->editCoupon($k, array($field => $v) );
			    } else {
					$dd = new ADispatcher('responses/error/ajaxerror/validation',array('error_text'=>$err));
					return $dd->dispatch();
			    }
            }
        }


		//update controller data
        $this->extensions->hk_UpdateData($this,__FUNCTION__);
	}

	private function _validateForm( $field, $value) {

		$err = false;
		switch( $field ) {
			case 'coupon_description' :
				foreach ($value as $language_id => $v) {
					if ( isset($v['name']) )
					if ((strlen(utf8_decode($v['name'])) < 2) || (strlen(utf8_decode($v['name'])) > 64)) {
						$err = $this->language->get('error_name');
					}

					if ( isset($v['description']) )
					if (strlen(utf8_decode($v['description'])) < 2) {
						$err = $this->language->get('error_description');
					}
				}
				break;
			case 'code':
				if ((strlen(utf8_decode($value)) < 2) || (strlen(utf8_decode($value)) > 10)) {
					$err = $this->language->get('error_code');
				}
				break;
		}

		return $err;
  	}


	public function products() {

        //init controller data
        $this->extensions->hk_InitData($this,__FUNCTION__);

		$this->loadModel('catalog/product');

		if (isset($this->request->post['id'])) { // variant for popup listing
			$products = $this->request->post['id'];
		}else {
			$products = array();
		}
		$product_data = array();

		foreach ($products as $product_id) {
			$product_info = $this->model_catalog_product->getProduct($product_id);

			if ($product_info) {
				$product_data[] = array(
					'id' => $product_info['product_id'],
					'product_id' => $product_info['product_id'],
					'name'       => $product_info['name'],
					'model'      => $product_info['model']
				);
			}
		}

        //update controller data
        $this->extensions->hk_UpdateData($this,__FUNCTION__);

		$this->load->library('json');
		$this->response->setOutput(AJson::encode($product_data));
	}
}