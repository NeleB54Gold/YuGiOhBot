<?php
	
# Ignore inline messages (via @)
if ($v->via_bot) die;

# Start YuGiOhAPI class
$ygo = new YuGiOhAPI($db, $user['lang']);

# Private chat with Bot
if ($v->chat_type == 'private') {
	if ($bot->configs['database']['status'] and $user['status'] !== 'started') $db->setStatus($v->user_id, 'started');
	
	# Delete saved results
	if ($v->isAdmin() and $v->command == 'delcache') {
		$db->rdel($db->rkeys($ygo->endpoint . '*'));
		$bot->sendMessage($v->chat_id, '✅');
	}
	# Start message
	elseif (in_array($v->command, ['start', 'start inline']) or $v->query_data == 'start') {
		$buttons[][] = $bot->createInlineButton('🔎 ' . $tr->getTranslation('tryMeInline'), ' ', 'switch_inline_query_current_chat');
		$buttons[] = [
			$bot->createInlineButton('ℹ️ ' . $tr->getTranslation('aboutButton'), 'about'),
			$bot->createInlineButton('🆘 ' . $tr->getTranslation('helpButton'), 'help')
		];
		$buttons[][] = $bot->createInlineButton('🔡 ' . $tr->getTranslation('changeLanguage'), 'lang');
		$t = $tr->getTranslation('startMessage');
		if ($v->query_id) {
			$bot->editText($v->chat_id, $v->message_id, $t, $buttons, 'def', 0);
			$bot->answerCBQ($v->query_id);
		} else {
			$bot->sendMessage($v->chat_id, $t, $buttons, 'def', 0);
		}
	}
	# Help message
	elseif ($v->command == 'help' or $v->query_data == 'help') {
		$buttons[][] = $bot->createInlineButton('◀️ ' . $tr->getTranslation('backButton'), 'start');
		$link_preview = $bot->text_link(' ', 'https://telegra.ph/file/cfbf8255c625f8b152ee4.jpg');
		$t = $link_preview . $tr->getTranslation('helpMessage');
		if ($v->query_id) {
			$bot->editText($v->chat_id, $v->message_id, $t, $buttons, 'def', 0);
			$bot->answerCBQ($v->query_id);
		} else {
			$bot->sendMessage($v->chat_id, $t, $buttons, 'def', 0);
		}
	}
	# About message
	elseif ($v->command == 'about' or $v->query_data == 'about') {
		$buttons[][] = $bot->createInlineButton('◀️ ' . $tr->getTranslation('backButton'), 'start');
		$t = $tr->getTranslation('aboutMessage', [explode('-', phpversion(), 2)[0]]);
		if ($v->query_id) {
			$bot->editText($v->chat_id, $v->message_id, $t, $buttons);
			$bot->answerCBQ($v->query_id);
		} else {
			$bot->sendMessage($v->chat_id, $t, $buttons);
		}
	}
	# Change language
	elseif ($v->command == 'lang' or $v->query_data == 'lang' or strpos($v->query_data, 'changeLanguage-') === 0) {
		$langnames = [
			'en' => '🇬🇧 English',
			'es' => '🇪🇸 Español',
			'fr' => '🇫🇷 Français',
			'pt' => '🇧🇷 Português',
			'it' => '🇮🇹 Italiano'
		];
		if (strpos($v->query_data, 'changeLanguage-') === 0) {
			$select = str_replace('changeLanguage-', '', $v->query_data);
			if (in_array($select, array_keys($langnames))) {
				$tr->setLanguage($select);
				$user['lang'] = $select;
				$db->query('UPDATE users SET lang = ? WHERE id = ?', [$user['lang'], $user['id']]);
			}
		}
		$langnames[$user['lang']] .= ' ✅';
		$t = '🔡 Select your language';
		$formenu = 2;
		$mcount = 0;
		foreach ($langnames as $lang_code => $name) {
			if (isset($buttons[$mcount]) and count($buttons[$mcount]) >= $formenu) $mcount += 1;
			$buttons[$mcount][] = $bot->createInlineButton($name, 'changeLanguage-' . $lang_code);
		}
		$buttons[][] = $bot->createInlineButton('◀️ ' . $tr->getTranslation('backButton'), 'start');
		if ($v->query_id) {
			$bot->editText($v->chat_id, $v->message_id, $t, $buttons, 'def', 0);
			$bot->answerCBQ($v->query_id);
		} else {
			$bot->sendMessage($v->chat_id, $t, $buttons, 'def', 0);
		}
	}
	# No text
	elseif ($v->command) {
		$link_preview = $bot->text_link(' ', 'https://telegra.ph/file/cfbf8255c625f8b152ee4.jpg');
		$t = $link_preview . $tr->getTranslation('helpMessage');
		$bot->sendMessage($v->chat_id, $t, [], 'def', 0);
	}
	# Search by text
	elseif ($v->text) {
		if (is_numeric($v->text)) {
			$cards = $ygo->cardInfo('id', $v->text, 1);
		} else {
			$cards = $ygo->cardInfo('fname', $v->text, 1);
		}
		if ($cards['ok'] and !empty($cards['result'])) {
			$buttons[][] = $bot->createInlineButton('💬 ' . $tr->getTranslation('shareButton'), '🔍' . $cards['result'][0]['id'], 'switch_inline_query');
			$bot->sendPhoto($v->chat_id, $cards['result'][0]['card_images'][0]['image_url'], null, $buttons);
		} else {
			$bot->sendMessage($v->chat_id, '❌ ' . $tr->getTranslation('noResult'));
		}
	}
	# Delete unknown message
	else {
		$bot->deleteMessage($v->chat_id, $v->message_id);
	}
}
# Unsupported chats (Auto-leave)
elseif (in_array($v->chat_type, ['group', 'supergroup', 'channels'])) {
	$bot->leave($v->chat_id);
	die;
}
# Inline commands
elseif ($v->update['inline_query']) {
	$results = [];
	if (!$v->offset) $v->offset = 0;
	$limit = 50;
	$sw_text = $tr->getTranslation('searchInline');
	$sw_arg = 'inline'; // The message the bot receive is '/start inline'
	$ygo->r_timeout = 2;
	# Search Yu-Gi-Oh cards with inline mode
	if (strpos($v->query, '🔍') === 0) {
		$cards = $ygo->cardInfo('id', str_replace('🔍', '', $v->query), $limit, $v->offset);
	} else {
		if (empty($v->query)) $v->query = ' ';
		$cards = $ygo->cardInfo('fname', $v->query, $limit, $v->offset);
	}
	if ($cards['ok'] && !empty($cards['result'])) {
		foreach ($cards['result'] as $id => $card) {
			$results[] = $bot->createInlinePhoto(
				$i += 1,
				'',
				'',
				$card['card_images'][0]['image_url'],
				'',
				'',
				[[$bot->createInlineButton('💬 ' . $tr->getTranslation('shareButton'), '🔍' . $card['id'], 'switch_inline_query')]],
				$card['card_images'][0]['image_url_small']
			);
		}
		$next = (count($cards['result']) == $limit) ? $v->offset + 1 : false;
	} else {
		$next = false;
	}
	if (empty($results)) $sw_text = '❌ ' . $tr->getTranslation('noResult');
	$bot->answerIQ($v->id, $results, $sw_text, $sw_arg, $next);
}

# Get the chosen results to count stats
elseif ($v->update['chosen_inline_result']) {
	# file_put_contents('stats.txt', file_get_contents('stats.txt') + 1);
}

?>
