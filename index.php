<?php
//use \PDO; //раскоментировать, если не работает на сервере
// работа с базой данных
class DBModel
{
	const DB_HOST = '127.0.0.1';
	const DB_USER = 'root';
	const DB_PASSWORD = '';
	const DB_NAME = 'matrix_test';	
	
	const CHARSET = 'utf8';
	const DB_PREFIX = '';
	private $query = null;
 	
	private $db;
	private static $instance = null;
	
	public static function Instance(){
		if(self::$instance == null){
			self::$instance = new DBModel();			
		}
		return self::$instance;
	}
  
	public function __construct(){		
		$this->db = new PDO(
				'mysql:host='.self::DB_HOST.';dbname='.self::DB_NAME,	self::DB_USER, self::DB_PASSWORD,
				$options = [
					PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
					PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
					PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES ".self::CHARSET
				]
			);			
	}
	
	public function sqlQuery($sql)
	{
		$this->query = $this->db->prepare($sql);
		$this->query->execute();
		return $this;
	}	
		
	public function fetchAllResult()
	{
		return $arr = $this->query->fetchAll();
	}	
}
// получение комбинированных строк
class Mixer {
	private $result = [];	
	private $startWatch = false;
	private $midWatch = false;
	private $endWatch = false;
	private $stringEndWatch = false;
	private $startsCounter = 0;
	private $endsCounter = 0;
	private $errors= []; 
	
	public function startMixer($data){		
		if (is_array($data)) {			
			foreach ($data as $value){				
				$value = $this -> mixText($value);				
				$this->startMixer($value);
			}		
			return ['result' => $this->result, 'errors' => $this->errors];		
		}
		$this->result[] = $data;		
	}

	private function mixText($text){		
		$semiResult = [];
		$tempArr = [];
		
		$this->startsCounter = 0;
		$this->endsCounter = 0;
		$this->startWatch = false;
		$this->midWatch = false;
		$this->endWatch = false;
		$this->stringEndWatch = false;
		$start = '';
		$exp = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);  				
			
		for($i = 0; $i < count($exp); $i++){				
			if ($exp[$i] == "<"){
				$this->checkErrors();								
				if($this->startsCounter === $this->endsCounter){								
					$start = implode($tempArr);					
					$this->startWatch = true;					
					$tempArr = [];					
				}else{
					$tempArr[] = $exp[$i];
				}
				$this->startsCounter++;
			}elseif($exp[$i] == ":" && $exp[$i+1] == ":"){
				if($this->startsCounter === $this->endsCounter + 1){					
					$semiResult[] = $start . implode($tempArr);
					$tempArr = [];
					$this->midWatch = true;
				}else{
					$tempArr[] = $exp[$i];
				}					
			}elseif($exp[$i] == ":" && $exp[$i-1] == ":"){
				if($this->startsCounter === $this->endsCounter + 1){
					continue;					
				}else{
					$tempArr[] = $exp[$i];
				}							
			}elseif($exp[$i] == ">"){
				$this->endsCounter++;
				$this->checkErrors();
				if($this->startsCounter === $this->endsCounter){					
					$this->endWatch = true;
					$this->checkErrors();
					$semiResult[] = $start . implode($tempArr);							
					$tail = implode(array_slice($exp, $i + 1));
					foreach($semiResult as &$value){
						$value .= $tail;
					}
					$tempArr[] = [];	
					break;				
				}else{
					$tempArr[] = $exp[$i];
				}
			}else{
				$tempArr[] = $exp[$i];				
			}
			if($i+1 == count($exp)){				
				$this->stringEndWatch = true;
				$this->checkErrors();
				$semiResult = implode($tempArr);							
				$tempArr = [];
			}						
		}		
		return $semiResult;		
	}
	
	private function checkErrors(){
		if($this->startsCounter < $this->endsCounter){
			//throw new Exception("Отсутствует открывающая скобка (<)");
			$this->errors[] = "Отсутствует открывающая скобка (<)";					
		}
		if($this->startWatch == true and $this->endWatch == false and $this->stringEndWatch == true){
			//throw new Exception("Отсутствует закрывающая скобка (>)");
			$this->errors[] = "Отсутствует закрывающая скобка (>)";
		}
		if($this->startWatch == true && $this->midWatch == false && $this->endWatch == true){
			//throw new Exception("Отсутствует разделитель (::)");
			$this->errors[] = "Отсутствует разделитель (::)";
		}
	}	
}
// управление работой страницы
class Controller {		
	private $errors = [];	
	private $allStrings;

	public function writeDB(){		
		$mixer = new Mixer();
		$this->inputValue = trim($_POST['text']);
		$res = $mixer -> startMixer([$this->inputValue]);
		$this->errors = $res['errors'];
		if(empty($res['errors'])){
			$db = DBModel::Instance();
			foreach($res['result'] as $one){				
				$check = $db->sqlQuery("SELECT * FROM `strings` WHERE string = '".$one."'")->fetchAllResult();				
				if(!$check){
					$db->sqlQuery("INSERT INTO `strings` (`string`) VALUES ('".$one."')");
				}				
			}
			header('Location: ./');
			exit;			
		}
		$this->getMainPage();
	}

	public function getMainPage(){
		$db = DBModel::Instance();
		$sql = "SELECT * FROM `strings`";		
		$this->allStrings = $db->sqlQuery($sql)->fetchAllResult();		
		echo $this->render(['allStrings' => $this->allStrings, 'errors' => $this->errors]);
	}

	public function render($values = [])
	{		
		ob_start();
		extract($values);
?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<meta http-equiv="X-UA-Compatible" content="IE=edge">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title>Document</title>			
		</head>
		<body>
			<div class="errors">
			<?if(!empty($errors)):?>
				<p>Произошла ошибка. Проверьте текст.</p>
				<?foreach($errors as $error):?>
				<p><?=$error?></p>
				<?endforeach;?>
			<? endif;?>	
			</div>
			<form action = "?write" method = "POST">
				<input type="text" name="text" value=<?=$inputValue?>>
				<input type="submit" value="Gen">
			</form>
			<table class = "results">
				<thead>
					<tr>
						<th>Номер</th>
						<th>Строка</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach($allStrings as $string):?>
					<tr>
						<td><?= $string["id"]?></td>
						<td><?= $string["string"]?></td>
					</tr>
					<?php endforeach;?>
				</tbody>
			</table>			
		</body>
		</html>
		
<?				
		return ob_get_clean();
	}
}

//роутинг
$a = new Controller();
if (isset($_GET['write'])){	
	$a -> writeDB();	
}else{	
	$a -> getMainPage();
}

