<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Note: To work this library it required to use the form Multipart html element.
 */
class Uploader{

	protected $ci;
	protected $base_path = 'uploads/';
	protected $allowed_config = [
		'upload' => [
			'upload_path',
			'allowed_types',
			'max_size',
			'max_width',
			'max_height',
			'overwrite',
			'file_name'
		],
		'resize' => [
			'maintain_ratio',
			'image_library',
			'create_thumb',
			'master_dim'
		]
	];
	protected $folder_path;
	protected $target_path;
	protected $file_path;
	protected $errors = [];
	protected $config = [];
	protected $resize_config = [];
	protected $rotation_config = [];

	function __construct(){
		$this->ci =& get_instance();
		$this->ci->load->library(['upload', 'image_lib']);
	}

	function config($config=[]){
		if(is_array($config) AND count($config)){

			if(array_key_exists('upload_path', $config)) $this->folder_path = $config['upload_path'];
			$config['upload_path'] = $this->generate_target_path();

			$this->check_create_folder();

			foreach($config as $index=>$value){

				if(in_array($index, $this->allowed_config['upload']) AND $value) $this->config[$index] = $value;
				if(in_array($index, $this->allowed_config['resize']) AND ($value OR is_bool($value))) $this->resize_config[$index] = $value;
			}


			return $this;
		}

		return $this->config;
	}

	function file_path($path=NULL){
		if($path){
			$this->file_path = $path;

			return $this;
		}

		return $this->file_path;
	}

	function folder_path($path=NULL){
		if($path){
			$this->folder_path = $path;
			return $this;
		}

		return $this->folder_path;
	}

	function run($field_name=NULL){
		$this->ci->upload->initialize($this->config);

		if($this->ci->upload->do_upload($field_name)){
			$source_file = $this->object('uploaded_file_path');
			$this->file_path($source_file); // set file path in global variable
			return TRUE;
		}
		else{
			$this->errors[$field_name] = $this->ci->upload->display_errors(NULL, NULL);
			return FALSE;
		}
	}

	function dimension($width=0, $height=0, $quality=100){
		$source = $this->file_path();
		$config = [];

		$config['width'] = ($width) ? (int)$width : 0;
		$config['height'] = ($height) ? (int)$height : 0;
		$config['quality'] = ($quality AND (int)$quality <= 100) ? (int)$quality : 50;

		if(file_exists($source)){
			$config['source_image'] = $source;

			if(!$config['width'] OR !$config['height']){
				$dimension = getimagesize($source);
				$file_size = filesize($source); // return int bytes
				$file_size = ceil($file_size / 1024);
				$width_height_limit = 1024;

				$ratio = $dimension[0] / $dimension[1]; // width / height of the original file

	            $is_width_height_large = (($dimension[0] >= $width_height_limit OR $dimension[1] >= $width_height_limit) AND (!$width AND !$height)) ? TRUE : FALSE;

	            /*if($file_size >= 1024 AND $is_width_height_large){
	                if($dimension[0] > $dimension[1]){
	                	 $config['width'] = $width_height_limit;
	                	 $config['height'] = NULL;
	                }
	                else{
	                	$config['width'] = NULL;
	                	$config['height'] = $width_height_limit;
	                }
	            }
	            else if($file_size >= 1024 AND !$is_width_height_large){
					if($dimension[0] > $dimension[1]){
	                	 $config['width'] = $dimension[0] - 1;
	                	 $config['height'] = NULL;
	                }
	                else{
	                	$config['width'] = NULL;
	                	$config['height'] = $dimension[1] - 1;
	                }
	            }*/

                if($is_width_height_large){
	                if($dimension[0] > $dimension[1]){
	                	 $config['width'] = $width_height_limit;
	                	 $config['height'] = NULL;
	                }
	                else{
	                	$config['width'] = NULL;
	                	$config['height'] = $width_height_limit;
	                }
	            }
	            else{
					if($dimension[0] > $dimension[1]){
	                	 $config['width'] = $dimension[0] - 1;
	                	 $config['height'] = NULL;
	                }
	                else{
	                	$config['width'] = NULL;
	                	$config['height'] = $dimension[1] - 1;
	                }
	            }

                if($config['width'] AND !$config['height']) $config['height'] = ceil($config['width'] / $ratio);           
                
                if($config['height'] AND !$config['width']) $config['width'] = ceil($config['height'] * $ratio);

                /*// check if width is set and dimension is greater than 2500 or the file size is greater than 1mb
                if(!$config['width'] AND ($dimension[0] >= 2500 OR $file_size >= 1000000)) $config['width'] = ceil($dimension[0] / 2);
                else $config['width'] = $dimension[0];

                // check if height is set and dimension is greater than 2500 or the file size is greater than 1mb
                if(!$config['height'] AND ($dimension[1] >= 2500 OR $file_size >= 1000000)) $config['height'] = ceil($dimension[1] / 2);
                else $config['height'] = $dimension[1];*/
			}

			$this->resize_config = array_merge($this->resize_config, $config);
		}

		return $this;
	}

	function resize(){
		$config = $this->resize_config;

		if(!array_key_exists('image_library', $config)) $config['image_library'] = 'gd2';
		if(!array_key_exists('master_dim', $config)) $config['master_dim'] = 'auto';

		if(file_exists($config['source_image'])){

			// print_array($config, 1);
			$image_lib = new CI_Image_lib();
			$image_lib->initialize($config);
			$image_lib->resize();
			$image_lib->clear(); // clear resize config

			if($image_lib->display_errors()) $this->errors[] = $image_lib->display_errors(NULL, NULL);
		}

		return FALSE;
	}

	function rotation_angle($angle=NULL, $source=NULL){
		$config = [];
		if(!$source) $source = $this->file_path();

		if(!$angle AND file_exists($source)){
			$image_data = @exif_read_data($source);

			if(isset($image_data['Orientation'])){
				switch($image_data['Orientation']) {
                    case 3:
                        $config['rotation_angle']='180';
                        break;
                    case 6:
                        $config['rotation_angle']='270';
                        break;
                    case 8:
                        $config['rotation_angle']='90';
                        break;
                }
			}
		}

		if(file_exists($source)) $config['source_image'] = $source;

		if(count($config)) $this->rotation_config = $config;

		return $this;
	}

	function rotate(){
		$config = $this->rotation_config;

		if(array_key_exists('rotation_angle', $config)){
			// print_array($config, 1);
			$image_lib = new CI_Image_lib();
			$image_lib->initialize($config);
			$image_lib->rotate();
			$image_lib->clear();

			if(!$image_lib->display_errors()) return TRUE;

			$this->errors[] = $image_lib->display_errors(NULL, NULL);
		}

		return FALSE;
	}

	function object($index=NULL){
		$data = $this->ci->upload->data(); 
		$data['base_url_file_path'] = sprintf('%1$s%2$s/%3$s', base_url(), $this->generate_target_path(), $data['file_name']);
		$data['uploaded_file_path'] = sprintf('%1$s/%2$s', $this->generate_target_path(), $data['file_name']);

		if($index){
			if(array_key_exists($index, $data)) return $data[$index];

			return NULL;
		}

		return (object)$data; 
	}

	function delete(){
		if(file_exists($this->file_path())){
			return unlink($this->file_path());
		}

		return FALSE;
	}

	function delete_files(){
		$target_path = $this->generate_target_path();
		$bool = FALSE;

		if(!str_is(['uploads', 'uploads/'], $target_path)){
			$target_path = rtrim($target_path, '/');

			if(is_dir($target_path)){
				$files = directory_map($target_path);

				if(count($files)){
					foreach($files as $file){
						if(@unlink(sprintf('%1$s/%2$s', $target_path, $file))) $bool = TRUE;
						else $bool = FALSE;
					}
				}
			}
		}

		return $bool;
	}

	function delete_folders(){
		$target_path = $this->generate_target_path();
		$bool = FALSE;

		if(!str_is(['uploads', 'uploads/'], $target_path)){
			if(is_dir($target_path)){
				$this->delete_files();
				if(@rmdir($target_path)) $bool = TRUE;

			}
		}

		return $bool;
	}

	function errors($index=NULL){

		if($index){
			if(array_key_exists($index, $this->errors)) return $this->errors[$index];
			return NULL;
		}

		return $this->errors;
	}

	function has_error(){
		return (count($this->errors)) ? TRUE : FALSE;
	}

	protected function check_create_folder(){
		if(!is_dir($this->generate_target_path())) {
			$path = explode('/', $this->generate_target_path());
			$generated_path = [];

			foreach($path as $segment) {
				$generated_path[] = $segment;
				$target_path = join('/', $generated_path);
				if(!is_dir($target_path)) mkdir($target_path); 
			}
		}
	}

	protected function generate_target_path(){
		$generate_target_path = NULL;

		if($this->folder_path){
			$this->folder_path = str_replace($this->base_path, '', $this->folder_path); // remove the base path in folder path
			$generate_target_path = sprintf('%1$s%2$s', $this->base_path, $this->folder_path);
		}
		else $generate_target_path = $this->base_path;

		if(!$this->target_path OR ($this->target_path !== $generate_target_path)) $this->target_path = $generate_target_path;

		return $this->target_path;
	}

	protected function generate_new_file_name(){

	}
}