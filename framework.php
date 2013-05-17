<?
class framework {
    private $enc_hash=null, $enc_type='AES-256-CBC', $iv_size=16;

//--construction
    public function __construct($db_connect=false){
        //set encryption breeze
        $this->breeze(1);

        //set encryption key
        $this->set_key('bob loblaw');

        //clean server objects
        $global_items = array('server'=>$_SERVER,'session'=>$_SESSION,'post'=>$_POST,'get'=>$_GET,'files'=>$_FILES,'cookie'=>$_COOKIE);
        foreach($global_items as $key=>$val) $this->$key = $this->convert_multi_array($val);
    }
//==construction

//--general functions
    //--encryption
        //--advanced encryption standard, high level 2 way encryption
            public function set_key($key,$hash_type='md5'){
                $this->enc_hash = hash($hash_type,$key);
            }

            //encrypts data with iv for highest level of security, returns value with iv pre-appended for storage
            public function encrypt($data){
                $iv = mcrypt_create_iv($this->iv_size, MCRYPT_RAND);
                $encrypted = openssl_encrypt($data, $this->enc_type, $this->enc_hash, 0, $iv);
                return $iv.$encrypted;
            }
            //set hash based off of input key, default is md5, requires 32 bit for default enc_type
            public function decrypt($data){
                $iv = substr($data, 0, $this->iv_size);
                $data = substr($data, $this->iv_size);
                $decrpyted = openssl_decrypt($data, $this->enc_type, $this->enc_hash, 0, $iv);
                return $decrpyted;
            }
        //==advanced encryption standard class, high level 2 way encryption

        //--low level encryption, 2 way hex string with offset option
            //set offset
            public function breeze($in=null){
                $this->wind = $in;
            }

            //encrypt value
            public function set($s,$r=''){
                $s = (string)$s;
                for ($i=0;$i<strlen($s);$i++){$r.=dechex(ord($s[$i])+$this->wind); }
                return $r;
            }
            //decrypt value
            public function get($h,$r=''){
                $h=(string)$h;
                for($i=0;$i<strlen($h)-1;$i+=2){$r.=chr(hexdec($h[$i].$h[$i+1])-$this->wind); }
                return $r;
            }
        //==low level encryption, 2 way hex string with offeset option  

        //--1 way encryption with a lil mix on it
            public function lockdown($u,$p){
                return hash(
                    $this->get('9d8e8f989296959592'),
                    str_pad(strrev($u).$p, 14, "_", STR_PAD_BOTH)
                );
            }
        //==1 way encryption with a lil mix on it
    //==encryption

    //--database
        public function mysql_linkup($obj){
            $link = mysql_connect(
                $this->get($obj->host), 
                $this->get($obj->user), 
                $this->get($obj->pass)
            ) or die('connect error.');
            $base = mysql_select_db($this->get($obj->base)) or die('db error.');
        }

        public function get_query_results($inbound,$single_array=true){
            $que = mysql_query($inbound);
            if(!preg_match('/^update/i',$inbound)){
                while($row=mysql_fetch_array($que)) $out[]=(object)$row;
                if(!empty($out) && count($out)>0){
                    if($single_array and count($out)==1) $out = $out[0];
                    return $out;
                } else return false;
            } else return $que;
        }
    //==database

    //--shortlinking (based off of integer value)
        public function alpha2int($a, $val=0){
            for($x=0; $x < strlen($a); $x++){
                $level = pow($this->base,((strlen($a)-1)-$x));
                $char  = array_search(substr($a, $x, 1),$this->chars);
                $val  += ($level==0) ? $char : ($level*$char);
            }
            return $val*($this->offset+1);
        }

        public function int2alpha($i, $out=''){
            $i = $i/($this->offset+1);
            $level = floor(log($i)/log($this->base));
            for($x=$level; $x >= 0; $x--){
                $out .= $this->chars[(floor($i/pow($this->base,$x)))];
                $i    = $i%pow($this->base,$x);
            }
            return $out;
        }
    //==shortlinking

    //--social
        public function convert_social_links($type,$inbound){
            $outbound = preg_replace('/(http:\/\/([\w-\.\/]+))/','<a href="$1" target="_blank">$1</a>',$inbound);
            if($type=='twitter'){
                $outbound = preg_replace('/@([\w]+)/','<a href="https://twitter.com/$1" target="_blank">@$1</a>',$outbound);
                $outbound = preg_replace('/#([\w]+)/','<a href="https://twitter.com/search?q=%23$1&src=hash" target="_blank">#$1</a>',$outbound);
            }
            return $outbound;
        }

        public function fql_init($fb_token,$fb_secret){
            $this->facebook = new stdClass();
            $this->facebook->token = $fb_token;
            $this->facebook->secret = $fb_secret;
        }
        
        public function get_social_source_json($type,$id){
            $json = new stdClass();
            $url = ($type=='facebook') 
                ? 'https://graph.facebook.com/fql?q=SELECT%20message%20FROM%20stream%20WHERE%20source_id%20%3D%20'.$id.'&access_token='.$this->facebook->token.'|'.$this->facebook->secret
                : 'https://api.twitter.com/1/statuses/user_timeline.json?include_entities=true&include_rts=true&screen_name='.$id;
            $json_source = json_decode(file_get_contents($url));
            
            if($type=='twitter') $json->data = $json_source;
            else $json = $json_source;

            $json->local_date = time();
            $json->local_type = $type;
            return json_encode($json);
        }

        public function get_social_json($type,$id){
            $local_path = 'res/json/'.$type.'.'.$id.'.json';
            if(file_exists($local_path)){
                $local_content = json_decode(file_get_contents($local_path));
                if(time()-$local_content->local_date>120){
                    $json = $this->get_social_source_json($type,$id);
                    if(!empty($json->error)){
                        $fallback = json_decode($json);
                        $fallback->local_date = time();
                        $content = json_encode($fallback);
                    } else $content = $this->get_social_source_json($type,$id);
                    $this->write_local_file($local_path,$content);
                }
            } else $this->write_local_file($local_path,$this->get_social_source_json($type,$id));
            return json_decode(file_get_contents($local_path));
        }

        public function format_social_json($type,$id,$limit){
            $json = $this->get_social_json($type,$id);
            $html = '';
            foreach($json->data as $item){
                if($count<$limit){
                    $count++;
                    if($type=='facebook') $content = $item->message;
                    elseif($type=='twitter') $content = $item->text;
                    $html .= '<div class="item"><div class="content">'.$this->convert_social_links($type,$content).'</div></div>';
                }
            }
            return $html;
        }
    //==social


    public function write_local_file($path,$content){
        $fh = fopen($path,'w');
        fwrite($fh,$content);
        fclose($fh);
    }

    public function curl($url,$json=false){
        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_URL, $url); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
        $out = curl_exec($ch); 
        curl_close($ch);    
        if($json) return(json_decode($out));
        else return($out);
    }

    public function convert_multi_array($array,$format='object',$case='lower-all'){
        if($format=='object'){
            $object = new stdClass();
            foreach($array as $key=>$val){
                if($case=='lower-all'){
                    $key = strtolower($key);
                    $val = (!is_array($val) && !is_object($val)) ? strtolower($val) : $val;
                }
                if(empty($val) || $val=='' || !isset($val)) $val = false;
                $object->$key = (is_array($val)) ? $this->convert_multi_array($val,$format,$case) : $val;
            }
            return $object;
        }
    }

    public function update_session($name,$val){
        $_SESSION[$name] = $val;
    }
//==general functions	
}
?>