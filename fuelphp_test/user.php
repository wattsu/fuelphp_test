<?php
class Controller_User extends Controller_Template{

	//beforeアクション
	public function before(){
    	parent::before();

		// POSTチェック
		$post_methods = array(
			'created',
			'updated',
			'removed',
			'confirm',
			'thanks',
			'/login/repass-info',
    	);
    	$method = Uri::segment(2);
    	if (Input::method() !== 'POST' && in_array($method, $post_methods)) {
			Response::redirect('auth/timeout');
    	}

    	// ログインチェック
    	$auth_methods = array(
			'logined',
			'logout',
			'update',
			'remove',
  		);
  		if (in_array($method, $auth_methods) && !Auth::check()) {
			Response::redirect('user/login/loginA');
  		}

  		// ログイン済みチェック
  		$nologin_methods = array(
			'login',
  		);
  		if (in_array($method, $nologin_methods) && Auth::check()) {
			Response::redirect('auth/logined');
  		}

		// CSRFチェック
  		if (Input::method() === 'POST' && !Security::check_token()) {
	  		Response::redirect('auth/timeout');
  		}

 		//アクションのパーミッション
 		$action = array('login', 'create', 'activate', 'timeout', 'autorepass', 'itemlist', 'cart', 'itemdetail', 'change', "thanks");

 		//アクション取得
 		$active = Request::active()->action;
 		//未ログインかつ許可アクション以外はログインページへ移動
 		if (!Auth::check() && !in_array($active,$action)) {
 			Response::redirect('user/login');
 		}
 	}


  	//商品一覧ページの表示
  	public function action_itemlist(){
  		if (!$vars = Session::get()) {
 			$vars = Input::post();
 			Session::set('vars', $vars);
		}
		$user = Model_User::userdata();
		$this->template = View::forge('templateA');
		$this->template->content = View::forge('user/itemlist');

 	}


 	public function action_thanks(){
 		if (!$vars = Session::get()) {
 			$vars = Input::post();
 			Session::set('vars', $vars);
		}
		$user = Model_User::userdata();

		if (empty($vars["cart"]) && !isset($vars["username"]) && $vars["is_order_done"] == "1") {
			//ログインページへ移動
			Response::redirect('const値');
		}

		if (Input::method() == 'POST') {
			if ($_POST['regist']) {
				$auth = Auth::instance();

				if ($auth->create_user($vars["username"], $vars["password"], $vars["email"], $vars["kana"], $vars["reemail"], $vars["tel"], $vars["repass"], $vars["zipcode"], $vars["pref"], $vars["addr"], $vars["payment"], $vars["delivery"], $vars["group"])) {
					$created = Model_User::find('first',array('where'=>array('email'=>$email)))->created_at;
					$total_price = "0";

					// カート内の合計金額を計算する。
					foreach ( $vars["cart"] as $cart ) {
						$total_price += $cart["price"] * $cart["num"];
					}

					$now = date("Y-m-d H:i:s");

					// DB格納
					$query = DB::insert('d_purchase')->set(array('username'=>$vars["username"],'purchase_date'=>$now,'total_price'=>$total_price,'postage'=>$vars["postage"]))->execute();
					$order_id = $query[0];
					foreach ( $vars["cart"] as $cart ) {
						$query = DB::insert('d_purchase_detail')->set(array('order_id'=>$vars["order_id"],'item_code'=>$cart["item_code"],'price'=>$cart["price"],'num'=>$cart["num"]))->execute();
					}
			
					Session::delete('cart');
					Session::set('is_order_done', '1');
				} elseif ( isset($_REQUEST["cmd"]) && $_REQUEST["cmd"] == "commit_order" && !$vars["cart"] == null) {
					$total_price = "0";
					$postage = $vars["postage"];
					// カート内の合計金額を計算する。
					foreach ( $vars["cart"] as $cart ) {
						$total_price += $cart["price"] * $cart["num"];
		 			}
		
					$now = date("Y-m-d H:i:s");
					// DB格納
					$query = DB::insert('d_purchase')->set(array('username'=>$vars["username"],'purchase_date'=>$now,'total_price'=>$total_price,'postage'=>$postage))->execute();
					$order_id = $query[0];		
					foreach ( $vars["cart"] as $cart ) {
						$query = DB::insert('d_purchase_detail')->set(array('order_id'=>$order_id,'item_code'=>$cart["item_code"],'price'=>$cart["price"],'num'=>$cart["num"]))->execute();
					}
					
 					//Eメールのインスタンス化
 					$sendmail=Email::forge();
 					$sendmail->from('const値','const値');
 					$sendmail->to($email,$username);
 					$sendmail->subject('const値');
 					$sendmail->html_body('メールテンプレート指定');
					$sendmail->send();
		
					Session::delete('cart');
					Session::delete('username');
					Session::delete('postage');
					Session::set('is_order_done', '1');
				} elseif ($_POST['return']) {
					Response::redirect('const値');
				} else {
					//データが保存されなかったら
					Session::set_flash('error', '予期せぬエラー発生により、ご購入手続きが完了されませんでした。大変お手数ですが、再度お手続き願います。
					なお、万が一、再度エラーが発生した場合は、弊社お問い合わせ窓口までご連絡ください。');
				}
			}
 			$this->template = View::forge('templateA');
 			$this->template->content=View::forge('user/thanks');
 		}
	}


 	public function action_cart(){
 		if (!$vars = Session::get()) {
 			$vars = Input::post();
 			Session::set('vars', $vars);
		}

 		$this->template = View::forge('templateA');
 		$this->template->content=View::forge('user/cart');
 	}


 	public function action_itemdetail(){
		$vars = Session::get();

    	$this->template = View::forge('templateA');
 		$this->template->content=View::forge('user/itemdetail');
	}


  	//ユーザー情報変更
 	public function action_change(){
   		$edit=array(
			'delivery'=>Input::post('delivery'),
 		);

    	$this->template = View::forge('templateA');
 		$this->template->content=View::forge('user/change');
	}


	public function action_login(){
 		if(Input::method() == 'POST') {
 			$auth=Auth::instance();
 				//資格情報の取得
 				if ($auth->login(Input::post('username'),Input::post('password'))) {
 					//禁止ユーザー判定
 					if (!$auth->has_access('user.index')) {
						Response::redirect('user/without');
 					}
 					Response::redirect('user/item_list');
 				} else {
 					Session::set_flash('error', 'ユーザー名かパスワードが違います。');
 				}
 		}
 		$theme = \Theme::forge();
 		$theme->set_template('templateB');
		$theme->get_template()->set('content',$theme->view('user/login'));
 		return $theme;
 	}


 	//ログアウト
 	public function action_logout(){
 		Auth::logout();
 		Session::destroy();

 		//ログインページへ移動
 		Session::set_flash('error', 'ログアウトしました。');
 		Response::redirect('http://sample.localhost/user/login/loginA');
 	}


 	public function action_activate($email,$created){
 		//個人データの取得
 		$active = Model_User::find('first',array(
 			'where'=>array('email'=>$email,'created_at'=>$created)));
 			//該当データの登録時間が48時間を過ぎていたら
 			if (time()>strtotime('+2 day',$created)) {
 				//タイムアウトページへ
 				Respose::redirect('user/timeout');
 			} elseif (count($active)>0) {
 				//groupパーミッションの変更
 				$active->group=1;
 				//データの保存
 				$active->save();
 				//loginページへ移動
 				Response::redirect('user/login');
 			}
 		return Model_User::theme('template','user/without');
 	}


 	public function action_without(){
 		//強制ログアウト
 		Auth::logout();
 		return Model_User::theme('template','/user/without');
 	}


 	public function action_autorepass(){
 		//POST送信なら
 		if (Input::method() == 'POST') {
 			//受信データの整理
 			$username = Input::post('username');
 			$email = Input::post('email');
 			//登録ユーザーの有無の確認
 			$user_count = Model_User::find()->where('username',$username)->where('email',$email)->count();
 
 			//該当ユーザーがいれば
 			if ($user_count>0) {
 				//Authのインスタンス化
 				$auth = Auth::instance();
 				//新しいパスワードの自動発行
 				$repass = $auth->reset_password($username); 
 				//送信データの整理
 				$data['repass'] = $repass;
 				$data['username'] = $username;
 				$data['email'] = $email;
 				$data['anchor'] = 'user/login/';
 				$body = View::forge('user/email/autorepass',$data);
 				//Eメールのインスタンス化
 				$sendmail = Email::forge();
 				//メール情報の設定
 				$sendmail->from('const値', 'const値');
 				$sendmail->to($email,$username);
 				$sendmail->subject('const値');
 				$sendmail->html_body($body);
 				//メールの送信
 				$sendmail->send();
 				//再発行手続きページへ移動
 				return Model_User::theme('template','user/repass-info');
 			}else{
 				//エラー表示
 				Session::set_flash('error', '該当者がいません。');
 			}
 		}
 	//テーマの表示
 	return Model_User::theme('template','user/autorepass');
 	}
 	
 	private function sess_ctrl(){
 	//confirm画面を追加する場合は、finishページと共通関数が発生するのでここに追加する
 	}
}