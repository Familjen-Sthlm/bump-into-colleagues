<?php
#!/usr/bin/php
date_default_timezone_set('Europe/Stockholm');

# INSTALLATION:
# - Get a token from Slack, paste it into the config below
# - Setup a cron job on your server to run this script every minute

# TODO (OPTIONAL - PLEASE MAKE PULL REQUEST IF YOU DO!): 
# - Create pagination if more users than 200

class SlackRoulette{

	function __construct(){

		# config
		$this->env = 'dev'; # Beware! "prod" creates real Slack messages...
		$this->token = 'xxx'; # Create a Slack App and get the token manually
		$this->teamname = 'xxx'; # Can be any hyphenated simple-text-string-that-looks-like-this-but-shorter
		$this->group_size = 3;
		$this->run_day = "Tuesday";
		$this->run_time = "09:01";

		# don't touch
		$this->teams = [];
		$this->users = [];
		$this->groups = [];

	}

	function run(){
		if(date('l') == $this->run_day && date('H:i') == $this->run_time){
			$this->log('Starting run...');
			$this->users = [];
			$this->groups = [];
			$this->get_users();
			$this->partition_users();
			$this->post_messages();
		}else{
			$this->log('Not set to run this time...');
		}
	}

	function get_users(){
		$all_users = json_decode(file_get_contents('https://slack.com/api/users.list?token='.$this->token));
		if($all_users->ok =='true'){
			foreach($all_users->members as $item){
				$skip = false;
				if($item->deleted == 'true'){
					$skip = true;
				}
				if(isset($item->is_restricted) && $item->is_restricted == 'true'){
					$skip = true;
				}
				if(isset($item->is_ultra_restricted) && $item->is_ultra_restricted == 'true'){
					$skip = true;
				}
				if($item->is_bot == 'true'){
					$skip = true;
				}
				if($item->is_app_user == 'true'){
					$skip = true;
				}
				if(isset($item->is_invited_user) && $item->is_invited_user == 'true'){
					$skip = true;
				}
				if($item->name == 'slackbot'){
					$skip = true;
				}
				if(!$skip){
					$status = json_decode(file_get_contents('https://slack.com/api/users.getPresence?token='.$this->token.'&user='.$item->id));
					if($status->presence == 'active'){
						$this->users[] = $item->id;
					}
				}
			}
		}
		$this->log('Found '.count($this->users).' users');
	}

	function partition_users(){
		shuffle($this->users);
		$this->groups = array_chunk($this->users, $this->group_size);
		$this->log('Made '.count($this->groups).' groups');
	}

	function post_messages(){
		if($this->groups){
			foreach($this->groups as $id=>$group){
				if(count($group) === $this->group_size){
					foreach($group as $user){
		
						$str = "Hey!\n";
						$str .= "Some people are hanging out in a on this call below.\n";
						$str .= "You have about 10 minutes to get to know them better. Use the time wisely. Show your true colors.\n";
						$str .= "Here are a few topics to talk about:\n";
						$str .= $this->random_subjects();
						$str .= "http://g.co/meet/bic-".$this->teamname."-w".date('W')."-".$id;
		
						$data['token'] = $this->token;
						$data['channel'] = $user;
						$data['text'] = $str;
						$data['unfurl_links'] = 'true';
						
						if($this->env == 'prod'){
							$ch = curl_init();
							curl_setopt($ch, CURLOPT_URL, "https://slack.com/api/chat.postMessage");
							curl_setopt($ch, CURLOPT_POST, true);
							curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
							curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
							$server_output = curl_exec($ch);
							curl_close ($ch);
						}
						
						$this->log($str);
					}
				}else{
					$this->log('A group was too small, leaving '.count($group).' users unmatched ('.json_encode($group).')');
				}
			}
		}		
	}

	function random_subjects(){
		$subjects = [
			'The monarchy',
			'Best movie ever',
			'A song a hate that i love',
			'My life in 10 years',
			'My biggest dream',
			'Art - overrated?',
			'Where does music come from?',
			'Someone who important in my teens',
			'A secret of mine',
			'The secret to creativity',
			'The meaning of life',
			'What is free will',
			'I my spare time...',
			'Dream vacation',
			'When I grow up I will...',
			'When I retire I will...',
			'I would like to know how to...'
		];
		shuffle($subjects);
		return "- ".$subjects[0]."\n"."- ".$subjects[1]."\n";
	}

	function log($message){
		file_put_contents('./bump_into_colleagues_'.date("Ymd").'.log', date('H:i:s')." ".$message."\n", FILE_APPEND);
	}

}

$sr = new SlackRoulette;
$sr->run();