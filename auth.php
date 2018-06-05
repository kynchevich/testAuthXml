<?php
ini_set('session.use_strict_mode', true);
session_start();

$user=new user();

if (!$_SESSION['USER_AUTH']) {
	if ($_POST['action']=='register') {
		$user->register();
		die();
	}

	if ($_POST['action']=='login') {
		$user->login();
		die();
	}
}

class User{
	public  $xmlIterator,$path,$errors=array(),$sucsess="";

	public function __construct()
	{	
		$this->fpath='base.xml';
		if(file_exists($this->fpath)){
			$this->xmlIterator=simplexml_load_file($this->fpath);
		}else{
			$this->errors[]='XML файл не найден';
		}
	}

	public function generateSalt()
	{
	    return substr(md5(uniqid('some_prefix', true)), 1, 10);
	}

	public function hashPassword($salt, $password)
	{
	    return md5($salt . $password);
	}

	public function confirmPassword($hash, $salt, $password)
	{
	    return $this->hashPassword($salt, $password) == $hash;
	}

	public function getUserByLogin($xmlIterator)
	{
		if ($user=$xmlIterator->xpath('//user[login[contains( text(),"'.$_POST['login'].'")]]')) {
			return $user;
		}else{
			return false;
		}
	}

	public function getUserByEmail($xmlIterator)
	{

		if ($user=$xmlIterator->xpath('//user[email[contains( text(),"'.$_POST['email'].'")]]')) {
			return $user;
		}else{
			return false;
		}
	}

	public function sessionDataAdd($user)
	{
		$_SESSION['USER_AUTH']=true;
		$_SESSION['USER']['login']=(string)$user[0]->login;
		$_SESSION['USER']['name']=(string)$user[0]->name;
	}

	public function addNewUser()
	{

		$salt=$this->generateSalt();
		$hash=$this->hashPassword($salt, $_POST['password']);
		$newUser=$this->xmlIterator->addChild('user');
		$newUser->addChild('login',$_POST['login']);
		$newUser->addChild('password',$hash);
		$newUser->addChild('email',$_POST['email']);
		$newUser->addChild('name',$_POST['user_name']);
		$newUser->addChild('salt',$salt);
		$newUser->addChild('sessid','');
		return $newUser;
	}

	public function returnRespose()
	{
		if($this->errors){
			$response['status']=0;
			$response['message']=implode(" ", $this->errors);
		}else{
			$response['status']=1;
			$response['message']=$this->sucsess;
		}
		echo json_encode($response);
	}
	 
	public function register()
	{
		if($this->getUserByLogin($this->xmlIterator))
			$this->errors[]='Пользователь с таким login существует';
		if($this->getUserByEmail($this->xmlIterator))
			$this->errors[]='Пользователь с таким email существует';
		if($_POST['password']!==$_POST['confirm_password'])
			$this->errors[]='Пароли не совпадают';

		if(!$this->errors){
			$this->addNewUser($this->xmlIterator);
			$this->xmlIterator->asXML($this->fpath);
			$this->sucsess='Поздравляем вы успешно зарегистрировались';
		}

		$this->returnRespose();
	}

	public function login()
	{
		if($user=$this->getUserByLogin($this->xmlIterator)){
			if($this->confirmPassword($user[0]->password, $user[0]->salt, $_POST['password'])){
				$user[0]->sessid=session_id();
			}else{
				$this->errors[]='Не верный пароль или имя пользователя';
			}
		}else{
			$this->errors[]='Не верный пароль или имя пользователя';
		}

		if(!$this->errors){
			$this->sucsess='Поздравляем '.$user[0]->name.' вы успешно авторизовались';
			$this->sessionDataAdd($user);
			$this->xmlIterator->asXML($this->fpath);
		}
		$this->returnRespose();
	}
}
?>
<!DOCTYPE HTML>
<html>
<head>
    <meta charset="utf-8">
    <title>Регистрация</title>
</head>
<body>

	<script>
		function formSubmit(element){
			var form=element.closest("form");
			var noValidInput;
			form.querySelectorAll("input").forEach(function(item, i, arr) {
				if(!item.checkValidity() && !noValidInput){
					noValidInput=item;
				}
			})

			if (noValidInput) {
				noValidInput.reportValidity();
				return false;
			}else{
				ajaxSubmit(form)
			}

		};

		function ajaxSubmit(form){
			var xhr = new XMLHttpRequest();
			var formData=new FormData(form);
			xhr.open("POST", "/auth.php",true);
			xhr.onload = function () {
			    if (xhr.readyState === xhr.DONE) {
			        if (xhr.status === 200) {
			        	response=JSON.parse(xhr.responseText);
			        	if(response.status){
			        		document.querySelector('.block_form').style.display="none";
			        		document.querySelector('h2').innerText=response.message;
			        	}else{
			        		document.querySelector('h2').innerText=response.message;
			        	}
			            console.log(JSON.parse(xhr.responseText));
			        }
			    }
			};
			xhr.send(formData);
		}
	</script>
	
	<?if ($_SESSION['USER_AUTH']): ?>
		<h2>Hello <?=$_SESSION['USER']['name']?></h2>
	<?else: ?>
		<h2></h2>
		<div class="block_form">
			<form method="post">
				<h3>Форма регистрации</h3>

				<input type="hidden" name='action' method value='register'>
				<div><label>Логин<input required type="text" name="login"></label></div>
				<div><label>Пароль<input required type="password" name="password"></label></div>
				<div><label>Подтверждение пароля<input required type="password" name="confirm_password"></label></div>
				<div><label>email<input required type="email" name="email"></label></div>
				<div><label>Имя пользователя<input type="text" name="user_name"></label></div>
				<div><button type="button" onclick="formSubmit(this);return false;">Зарегистрироваться</button></div>
			</form>

			<form method="post">
				<h3>Форма авторизации</h3>
				<input type="hidden" name='action' value='login'>
				<div><label>Логин<input required type="text" name="login"></label></div>
				<div><label>Пароль<input required type="password" name="password"></label></div>
				<div><button type="button" onclick="formSubmit(this);return false;">Зарегистрироваться</button></div>
			</form>
		</div>
	<?endif ?>
</body>
</html>


	

