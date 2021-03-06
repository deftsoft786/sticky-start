<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Store extends CI_Controller
{
    
    
	function __construct()
	{
		parent::__construct();
        $this->load->helper(array('form', 'url'));
		$this->load->library('tank_auth');
        $this->load->library('form_validation');
		$this->load->library('security');
        $this->load->library('datatables');
        $this->lang->load('tank_auth');
        $this->load->model('store_model');
        $this->load->model('manage_stores_model');
    }
    

    /**
   * Loads the Store owner list core
   *
   * @return void
   */
  function index()
  {
        $data["admin_data"] = $this->verify_for_direct_request();
        $data["title"]  = "Store Owner";
        $this->load->view('includes/inner_header', $data);
        $this->load->view('store/store_owner_list', $data);
        $this->load->view('includes/inner_footer', $data);
  }




    /**
    * Load Store owner list for datatable
    *
    * @return json
    */
  function get_store_owner_list()
    {
        $data["admin_data"] = $this->verify_for_direct_request();
        $looged_in_user_id=$this->tank_auth->get_user_id();
        $add_on = "is_deleted <> 1 AND role = 3";
        $this->datatables->select('first_name, last_name, email,v2_users.created, v2_users.id ')->join('v2_user_profiles', 'v2_users.id = v2_user_profiles.user_id','LEFT')->from('v2_users')->where($add_on);
        echo $this->datatables->generate();
    }
    
    /**
     * View store owner details
     * @param int
     * @return array()
     * */
    
    public function view_member($uid)
    {
        $data["admin_data"] = $this->verify_for_direct_request();
        $data["details"] = $this->store_model->view_member($uid);
        $this->load->view('store/view_member',$data);  
    }
      /**
    * Load Store list for datatable
    *
    * @return json
    */
  function get_store_list($store_id)
    {
        $data["admin_data"] = $this->verify_for_direct_request();
        $data = array('id'=>$store_id);
        $this->datatables->select('v2_stores.store_name,v2_stores.created,v2_stores.status, v2_users.id,v2_stores.store_id ')
        ->join('v2_stores', 'v2_stores.created_by = v2_users.id','LEFT')
        ->from('v2_users')
        ->where($data)
        ->where('v2_stores.is_deleted',0);
        echo $this->datatables->generate();
    }
        /**
     * View store list
     * @param int
     * @return array()
     * */
    
    public function view_store($store_id)
    {
        $data["admin_data"] = $this->verify_for_direct_request();
        
        $data["title"]  = "Store List";
        $data["store_id"]=$store_id;
        $this->load->view('includes/inner_header', $data);
        $this->load->view('store/view_store', $data);
        $this->load->view('includes/inner_footer', $data);
    }
    /**
     * View store list
     * @param int
     * @return array()
     * */
    
    public function view_store_details($store_id)
    {
        $data["admin_data"] = $this->verify_for_direct_request();
        $data["w_hours"]=$this->manage_stores_model->get_working_hours($store_id);
        
        
       $data["details"] = $this->manage_stores_model->view_store($store_id);
        $this->load->view('store/view_store_details', $data);
       
    }
    
    
    
     /**
    * Generate form for edit store owner modal
    *
    * @return void
    */
    public function edit_user($uid,$type='basic')
    {
          $data["admin_data"] = $this->verify_for_direct_request();
          $data["details"] = $this->store_model->view_member($uid);
          $data["type"] = $type;
          $this->load->view('store/edit_member',$data);
     }
    
        
    
    /**
	 * Register store owner on the site
	 *
	 * @return void
	 */
	function add()
	{
	        $data["admin_data"] = $this->verify_for_direct_request();
			$this->form_validation->set_rules('email', 'Email address', 'trim|required|xss_clean|valid_email');
            $use_username = $this->config->item('use_username', 'tank_auth');
            if ($use_username) {
              $this->form_validation->set_rules('username', 'Username', 'trim|required|xss_clean|min_length['.$this->config->item('username_min_length', 'tank_auth').']|max_length['.$this->config->item('username_max_length', 'tank_auth').']|alpha_dash');
            }
            $this->form_validation->set_rules('password', 'Password', 'trim|required|xss_clean|min_length['.$this->config->item('password_min_length', 'tank_auth').']|max_length['.$this->config->item('password_max_length', 'tank_auth').']|alpha_dash');
			$this->form_validation->set_rules('confirm_password', 'Confirm Password', 'trim|required|xss_clean|matches[password]');
            $this->form_validation->set_rules('first_name', 'First Name', 'trim|xss_clean');
            $this->form_validation->set_rules('last_name', 'Last Name', 'trim|xss_clean');
           
            
            $data['errors'] = array();
            
			      $email_activation = $this->config->item('email_activation', 'tank_auth');
            $role = 3; //for store owner
            
        
			  if ($this->form_validation->run()) {								// validation ok
				if (!is_null($data = $this->tank_auth->create_user(
						$use_username ? $this->form_validation->set_value('username') : '',
						$this->form_validation->set_value('email'),
						$this->form_validation->set_value('password'),
            $email_activation,
            $role
            ))) {									// success

					$data['site_name'] = $this->config->item('website_name', 'tank_auth');
          $data['first_name'] = $this->form_validation->set_value('first_name');
          $data['last_name'] = $this->form_validation->set_value('last_name');
                    
                    $sent="";
					if ($email_activation) {									// send "activate" email
						$data['activation_period'] = $this->config->item('email_activation_expire', 'tank_auth') / 3600;

  						$this->_send_email('activate', $data['email'], $data);

						unset($data['password']); // Clear password (just for any case)
                        
						$this->_show_message($this->lang->line('auth_message_registration_completed_1'));

					} else {
						if ($this->config->item('email_account_details', 'tank_auth')) {	// send "welcome" email
                          //error_reporting(0);
                          if($this->input->post('send_mail'))
						  $sent = $this->_send_email('welcome', $data['email'], $data);
						}
						unset($data['password']); // Clear password (just for any case)
                        //$this->_show_message($this->lang->line('auth_message_registration_completed_2').' '.anchor('/auth/login/', 'Login'));
                        if($sent)
                        {
                          $result["status"] = 1; 
                          $result["message"] = $this->lang->line('new_owner_added','tank_auth'). '<br /> E-mail sent to: '.$data["email"];
                        }
                        else
                        {
                          $result["status"] = 1; 
                          $result["message"] = $this->lang->line('new_owner_added','tank_auth');
                        }
                        echo json_encode($result);
                        die();
					}
				} else {
				   $errors = $this->tank_auth->get_error_message();
                   foreach ($errors as $k => $v)	$data['errors'][$k] = $this->lang->line($v);
                   $result["status"] = 0;
                   $result["message"]= implode('&nbsp; >',$data['errors']);
                   echo json_encode($result);
                   die();
				}
			}
			 else
            {
              $result["status"] = 0;
              $result["message"] = "Error occured at this time!!. Please try again later.";
              echo json_encode($result);
              die();
            }
           
		
	
    }
    
    
    /**
    * Update store owner information
    *
    * @return void
    */
	function update($uid)
	{
	     $data["admin_data"] = $this->verify_for_direct_request();
         if(!$uid){
                  $result["status"] = 0;
                  $result["message"] ="Error occured at this time. Please try again later.";
                  echo json_encode($result);
                  die();
                }
                
                        $this->form_validation->set_rules('first_name', 'First Name', 'trim|xss_clean');
                        $this->form_validation->set_rules('last_name', 'Last Name', 'trim|xss_clean');
                        
                        $data['errors'] = array();
            
            			if ($this->form_validation->run()) {								// validation ok
                			if($this->store_model->update_member($uid))
                                    {
                                      $result["status"] = 1;
                                      $result["message"] = $this->lang->line('update_success');
                                    }
                                    else
                                    {
                                      $result["status"] = 0;
                                      $result["message"] = $this->lang->line('update_error');
                                    }
                            echo json_encode($result);
                            die();         
                        }
         
   }
    
    /**
	 * Update basic account details
	 *
	 * @return void
	 */
	function update_account_details($uid)
	{
	        $data["admin_data"] = $this->verify_for_direct_request();
            if(!$uid){
              $result["status"] = 0;
              $result["message"] ="Error occured at this time. Please try again later.";
              echo json_encode($result);
              die();
            } 
            
            $use_username = $this->config->item('use_username', 'tank_auth');
        			if ($use_username) {
        			 $this->form_validation->set_rules('username', 'Username', 'trim|required|xss_clean|min_length['.$this->config->item('username_min_length', 'tank_auth').']|max_length['.$this->config->item('username_max_length', 'tank_auth').']|alpha_dash');
        			}
        			$this->form_validation->set_rules('email', 'Email', 'trim|required|xss_clean|valid_email');
        		    $this->form_validation->set_rules('banned', 'banned', 'trim|xss_clean');
                    
                    $data['errors'] = array();
                    $role = 3;
        			$email_activation = $this->config->item('email_activation', 'tank_auth');
        
        			if ($this->form_validation->run()) {								// validation ok
        				if (!is_null($data = $this->tank_auth->update_acc_details($uid,
        						$use_username ? $this->form_validation->set_value('username') : '',
        						$this->form_validation->set_value('email'),
                                $this->form_validation->set_value('banned'),
                                $email_activation,$role))) {									// success
        
        					$data['site_name'] = $this->config->item('website_name', 'tank_auth');
        
        					if ($email_activation) {									// send "activate" email
        						$data['activation_period'] = $this->config->item('email_activation_expire', 'tank_auth') / 3600;
                                $this->_send_email('activate', $data['email'], $data);
                                $this->_show_message($this->lang->line('auth_message_registration_completed_1'));
                            } else {
        					   
        					  error_reporting(0);
                              if($this->input->post('send_mail'))
                              $sent = $this->_send_email('details_updated', $data['email'], $data);
        						if($sent)
                                {
                                  $result["status"] = 1; 
                                  $result["message"] = $this->lang->line('update_success')."<br /> E-mail sent to : ".$data['email'] ;
                                }
                                else
                                {
                                  $result["status"] = 1; 
                                  $result["message"] = $this->lang->line('update_success');
                                }
                                echo json_encode($result);
                                die();
        					}
        				} else {
        				   $errors = $this->tank_auth->get_error_message();
                           foreach ($errors as $k => $v)	$data['errors'][$k] = $this->lang->line($v);
                           $result["status"] = 0;
                           $result["message"] = implode('&nbsp; >',$data['errors']);
                           echo json_encode($result);
                           die();
        				}
        			}
                    else
                    {
                      $result["status"] = 0;
                      $result["message"] ="Error occured at this time!!. Please try again later.";
                      echo json_encode($result);
                      die();
                    }
     }
    
    /**
	 * Update store owner password by Compnay or company Admin
	 *
     * @param int 
	 * @return void
	 */
	function update_password($uid)
	{
		$data["admin_data"] = $this->verify_for_direct_request();
        if(!$uid){
                  $result["status"] = 0;
                  $data['errors'][0]= "Error occured at this time. Please try again later.";
                  $result["messsage"] =$data['errors'];
                  echo json_encode($result);
                  die();
        }
            
            
			$data['user_id']	= $this->tank_auth->get_user_id();
			$data['username']	= $this->tank_auth->get_username();
            
			$use_username = $this->config->item('use_username', 'tank_auth');
			$this->form_validation->set_rules('password', 'Password', 'trim|required|xss_clean|min_length['.$this->config->item('password_min_length', 'tank_auth').']|max_length['.$this->config->item('password_max_length', 'tank_auth').']|alpha_dash');
			$this->form_validation->set_rules('confirm_password', 'Confirm Password', 'trim|required|xss_clean|matches[password]');
            $this->form_validation->set_rules('email', 'Email', 'trim|xss_clean');
            $data['errors'] = array();

			$email_activation = $this->config->item('email_activation', 'tank_auth');

			if ($this->form_validation->run()) {								// validation ok
				if (!is_null($data = $this->tank_auth->update_password($uid,$this->form_validation->set_value('password'),$this->form_validation->set_value('email')))) {									// success

					$data['site_name'] = $this->config->item('website_name', 'tank_auth');

					  error_reporting(0);
                      if($this->input->post('send_mail'))  
					  $sent = $this->_send_email('pass_updated', $data['email'], $data);
						
						unset($data['password']); // Clear password (just for any case)
                        if($sent)
                        {
                          $result["status"] = 1; 
                          $result["message"] = $this->lang->line('update_success')."<br /> E-mail sent to : ".$data['email'] ;
                        }
                        else
                        {
                          $result["status"] = 1; 
                          $result["message"] = $this->lang->line('update_success');
                        }
                        echo json_encode($result);
                        die();
					
				} else {
				   $errors = $this->tank_auth->get_error_message();
                   foreach ($errors as $k => $v)	$data['errors'][$k] = $this->lang->line($v);
                   $result["status"] = 0;
                   $result["message"] = implode('&nbsp; >',$data['errors']);
                   echo json_encode($result);
                   die();
				}
			}
            else
            {
              $result["status"] = 0;
              $result["message"] = "Error occured at this time!!. Please try again later.";
              echo json_encode($result);
              die();
            }
    }
    
    
    
     
    /**
	 * Update password by Company itself
	 *
     * @int 
	 * @return void
	 */
    function change_password()
	{
          $data["admin_data"] = $this->verify_for_direct_request();
    	  $uid = $this->tank_auth->get_user_id();
          if(!$uid){
          $result["status"] = 0;
          $data['errors'][0]= "Error occured at this time. Please try again later.";
          $result["messsage"] =$data['errors'];
          echo json_encode($result);
          die();
          }
            
		$this->form_validation->set_rules('old_password', 'Oud Wachtwoord', 'trim|required|xss_clean');
		$this->form_validation->set_rules('password', 'Wachtwoord', 'trim|required|xss_clean|min_length['.$this->config->item('password_min_length', 'tank_auth').']|max_length['.$this->config->item('password_max_length', 'tank_auth').']|alpha_dash');
		$this->form_validation->set_rules('confirm_password', 'Bevestig wachtwoord', 'trim|required|xss_clean|matches[password]');

		$data['errors'] = array();

		if ($this->form_validation->run()) {								// validation ok
			if ($this->tank_auth->change_password(
					$this->form_validation->set_value('old_password'),
					$this->form_validation->set_value('password'))) {	// success
			      
                  $result["status"] = 1; 
                  $result["message"] = $this->lang->line('customer_updated');
                  echo json_encode($result);
                  die();
                  
			} else {
			   $errors = $this->tank_auth->get_error_message();
               foreach ($errors as $k => $v)	$data['errors'][$k] = $this->lang->line($v);
               $result["status"] = 0;
               $result["message"] = implode('&nbsp; >',$data['errors']);
               echo json_encode($result);
               die();
			}
		}
        else
        {
          $result["status"] = 0;
          $result["message"] = "Error occured at this time!!. Please try again later.";
          echo json_encode($result);
          die();
        }
     }
    
    
    
    
    /**
	 * Send email message of given type (activate, forgot_password, etc.)
	 *
	 * @param	string
	 * @param	string
	 * @param	array
	 * @return	void
	 */
	function _send_email($type, $email, &$data)
	{
		$this->load->library('email');
		$this->email->from($this->config->item('webmaster_email', 'tank_auth'), $this->config->item('website_name', 'tank_auth'));
		$this->email->reply_to($this->config->item('webmaster_email', 'tank_auth'), $this->config->item('website_name', 'tank_auth'));
		$this->email->to($email);
		$this->email->subject(sprintf($this->lang->line('auth_subject_'.$type), $this->config->item('website_name', 'tank_auth')));
		$this->email->message($this->load->view('email/'.$type.'-html', $data, TRUE));
		$this->email->set_alt_message($this->load->view('email/'.$type.'-txt', $data, TRUE));
		return $this->email->send();
	}
     /**
     * Disapprove Store
     *
     * @param	int
     * @return	void
     */
    public function disapprove_store($store_id)
    { 
            
            $data["admin_data"] = $this->verify_for_direct_request();
            $is_deleted = $this->store_model->disapprove_store($store_id);
            if ($is_deleted) {
                $result["status"] = 1;
                $result["message"] = $this->lang->line('disapprove_store');
            } else {
                $result["status"] = 0;
                $result["message"] = $this->lang->line('update_error');
            }
        
        echo json_encode($result);
        die();
    }  
    /**
     * Approve store
     *
     * @param	int
     * @return	void
     */
    public function approve_store($store_id)
    {
        
            $data["admin_data"] = $this->verify_for_direct_request();
            $is_deleted = $this->store_model->approve_store($store_id);
            if ($is_deleted) {
                $result["status"] = 1;
                $result["message"] = $this->lang->line('approve_store');
            } else {
                $result["status"] = 0;
                $result["message"] = $this->lang->line('update_error');
            }
       
        echo json_encode($result);
        die();
    }
      
    
    /**
	 * Show info message
	 *
	 * @param	string
	 * @return	void
	 */
	function _show_message($message)
	{
		$this->session->set_flashdata('message', $message);
		redirect('/auth/');
	}
    
    
   
        
     /**
	 * Delete user and its profile
	 *
	 * @param	int
	 * @return	void
	 */
    public function delete($uid)
    {
        
        $data["admin_data"] = $this->verify_for_direct_request();
        $is_deleted = $this->store_model->delete_store_member($uid);
        if($is_deleted)
        {
         $result["status"] = 1; 
         $result["message"] = $this->lang->line('store_owner_delete_success');
        }
        else
        {
           $result["status"] = 0; 
           $result["message"] = $this->lang->line('update_error');
        }  
       echo json_encode($result);
       die();     
    }

      
     /**
      * Verify if user is logged in and is admin (for direct requests)
      * 
      **/
      private function verify_for_direct_request()
      {
        if (!$this->tank_auth->is_logged_in()) {
			redirect('/auth/login/');
            die();
		} 
        if(!$this->tank_auth->is_admin() && !$this->tank_auth->is_mod()){
                redirect('/auth/login/'); 
                die();
        }
        
        return  $this->session->all_userdata();   
      }   


     /**
      * Verify if user is logged in and is admin (for ajax requests)
      * 
      **/
      private function verify_for_ajax_request()
      {
        if (!$this->tank_auth->is_logged_in()) {
            echo "<div class='alert alert-danger'>Invalid Access Or Session Timed Out</div>";
            die();
              }
              
         if (!$this->tank_auth->is_admin() && !$this->tank_auth->is_mod()) {
            echo "<div class='alert alert-danger'>Invalid Access Or Session Timed Out</div>";
            die();
              }  
      } 
        
        
}

/* End of file user.php */
/* Location: ./application/controllers/user.php */