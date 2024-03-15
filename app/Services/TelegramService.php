<?php

namespace App\Services;

use App\Traits\HttpResponse;
use App\Utility\PrivateTelegramUtility;
use App\Utility\TelegramUtility;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TelegramService
{
    use HttpResponse;

    const CHANNEL_POST = 'channel_post';
    const CALLBACK_QUERY = 'callback_query';
    const EDITED_MESSAGE = 'edited_message';
    const INLINE_QUERY = 'inline_query';
    const MESSAGE = 'message';
    const PHOTO = 'photo';
    const VIDEO = 'video';
    const AUDIO = 'audio';
    const VOICE = 'voice';
    const CONTACT = 'contact';
    const LOCATION = 'location';
    const REPLY = 'reply';
    const ANIMATION = 'animation';
    const STICKER = 'sticker';
    const DOCUMENT = 'document';

    private string $bot_token = '';
    private static array $data = [];
    private array $updates = [];
    private ?int $user_id;
    private string $mode = 'markdown';


    /**
     * TelegramService constructor.
     * 
     * @description Initialize the bot token and get the data from the request
     */
    public function __construct()
    {
        $this->bot_token = config('services.telegram_bot.api_key');
        self::$data = $this->getData() ?? [];
        // $this->getChatType();

        // // call to TelegramUtility
        // TelegramUtility::handle($this, $this->getUpdateType());
    }


    /**
     * @param string $api
     * @param array $content
     * @param bool $post
     * @return array
     */
    private function endpoint(string $api, array $content, $post = true): array
    {
        $url = config('services.telegram_bot.endpoint') . $this->bot_token . '/' . $api;

        if ($post)
            $response = $this->sendAPIRequest($url, $content);
        else
            $response = $this->sendAPIRequest($url, [], false);

        return $response;
    }


    /**
     * developer mode
     */
    public function technicalWork(bool $status = false): void
    {
        if ($status === true) {
            if ($this->ChatID() != config('services.telegram_bot.admin_id')) {
                $this->sendMessage(text: 'Technical work, please try again later.');
                return;
            }
        }
    }


    public function isPrivateChat()
    {
        return $this->getChatType() === 'private';
    }


    public function isGroupChat(): bool
    {
        return $this->getChatType() === 'group';
    }


    public function isSuperGroupChat(): bool
    {
        return $this->getChatType() === 'supergroup';
    }


    /**
     * @param int $id
     * @description Set the user id
     */
    final public function setUserId(int $id): void
    {
        $this->user_id = $id;
    }


    /**
     * @return int|null
     * @description Get the user id
     */
    final public function getUserId(): int|NULL
    {
        if ($this->user_id) {
            return $this->user_id;
        } else {
            $this->setUserId($this->UserID());
            return $this->user_id;
        }
    }


    /**
     * @return string|null
     * @description Get the chat type
     */
    final public function getChatType(): string|NULL
    {
        if ($this->getUpdateType() === self::CALLBACK_QUERY) {
            if (self::$data[$this->getUpdateType()]['message']['chat']['type'] == 'private') {
                $this->setUserId($this->ChatID());
                return self::$data[$this->getUpdateType()]['message']['chat']['type'];
            } elseif (self::$data[$this->getUpdateType()]['message']['chat']['type'] == 'supergroup' or self::$data[$this->getUpdateType()]['message']['chat']['type'] == 'group') {
                $this->setUserId($this->UserID());
                return self::$data[$this->getUpdateType()]['message']['chat']['type'];
            }
        } elseif (
            $this->getUpdateType() === self::MESSAGE ||
            $this->getUpdateType() === self::PHOTO ||
            $this->getUpdateType() === self::VIDEO
        ) {
            if (self::$data['message']['chat']['type'] == 'private') {
                $this->setUserId($this->ChatID());
                return self::$data['message']['chat']['type'];
            } elseif (self::$data['message']['chat']['type'] == 'supergroup' or self::$data['message']['chat']['type'] == 'group') {
                $this->setUserId($this->UserID());
                return self::$data['message']['chat']['type'];
            }
        } elseif ($this->getUpdateType() === self::INLINE_QUERY) {
            if (self::$data['inline_query']['chat_type'] == 'private') {
                $this->setUserId($this->ChatID());
                return self::$data['inline_query']['chat_type'];
            } elseif (self::$data['inline_query']['chat_type'] == 'supergroup' or self::$data['inline_query']['chat_type'] == 'group') {
                $this->setUserId($this->UserID());
                return self::$data['inline_query']['chat_type'];
            }
        } else {
            $this->setUserId($this->ChatID());
            return 'private';
        }
    }


    /**
     * @return bool
     * @description Check if the user is admin
     */
    public function isAdmin(): bool
    {
        if ($this->ChatID() == config('services.telegram_bot.admin_id'))
            return true;
        else
            return false;
    }


    /**
     * @param string $text
     */
    public function UniqueStr(): string
    {
        return time() . Str::random(10) . '.jpg';
    }


    public function getMe()
    {
        return $this->endpoint('getMe', [], false);
    }


    /**
     * @param int|null $chat_id
     * @param string $text
     * @param string|null $parse_mode
     * @param bool $disable_web_page_preview
     * @param bool $disable_notification
     * @param int|null $reply_to_message_id
     * @param null $reply_markup
     * @return array
     */
    public function sendMessage(
        string $text,
        ?int $chat_id = NULL,
        ?string $parse_mode = NULL,
        bool $disable_web_page_preview = false,
        bool $disable_notification = false,
        ?int $reply_to_message_id = NULL,
        $reply_markup = null
    ): array {
        $content = [
            'chat_id' => is_null($chat_id) ? $this->getUserId() : $chat_id,
            'text' => $text,
            'parse_mode' => is_null($parse_mode) ? $this->mode : $parse_mode,
            'disable_web_page_preview' => $disable_web_page_preview,
            'disable_notification' => $disable_notification,
        ];

        if (!is_null($reply_to_message_id))
            $content['reply_to_message_id'] = $reply_to_message_id;

        if (!is_null($reply_markup))
            $content['reply_markup'] = $reply_markup;

        return $this->endpoint('sendMessage', $content);
    }


    public function forwardMessage(array $content)
    {
        return $this->endpoint('forwardMessage', $content);
    }


    /**
     * @param int|null $chat_id
     * @param string $photo
     * @param string|null $caption
     * @param string|null $parse_mode
     * 
     * @return array
     */
    public function sendPhoto(
        string $photo,
        ?string $caption = NULL,
        ?string $parse_mode = NULL,
        bool $disable_notification = false,
        ?int $reply_to_message_id = NULL,
        ?int $chat_id = NULL,
        $reply_markup = null
    ): array {
        $content = [
            'chat_id' => $chat_id ?? $this->getUserId(),
            'photo' => $photo,
            'caption' => $caption ?? '',
            'parse_mode' => $parse_mode ?? $this->mode,
            'disable_notification' => $disable_notification,
        ];

        if (!is_null($reply_to_message_id))
            $content['reply_to_message_id'] = $reply_to_message_id;

        if (!is_null($reply_markup))
            $content['reply_markup'] = $reply_markup;

        return $this->endpoint('sendPhoto', $content);
    }


    public function sendAudio(array $content)
    {
        return $this->endpoint('sendAudio', $content);
    }


    public function sendDocument(array $content)
    {
        return $this->endpoint('sendDocument', $content);
    }


    public function sendAnimation(array $content)
    {
        return $this->endpoint('sendAnimation', $content);
    }


    public function sendSticker(array $content)
    {
        return $this->endpoint('sendSticker', $content);
    }


    public function sendVideo(array $content)
    {
        return $this->endpoint('sendVideo', $content);
    }


    public function sendVoice(array $content)
    {
        return $this->endpoint('sendVoice', $content);
    }


    public function sendLocation(array $content)
    {
        return $this->endpoint('sendLocation', $content);
    }


    public function editMessageLiveLocation(array $content)
    {
        return $this->endpoint('editMessageLiveLocation', $content);
    }


    public function stopMessageLiveLocation(array $content)
    {
        return $this->endpoint('stopMessageLiveLocation', $content);
    }


    public function setChatStickerSet(array $content)
    {
        return $this->endpoint('setChatStickerSet', $content);
    }


    public function deleteChatStickerSet(array $content)
    {
        return $this->endpoint('deleteChatStickerSet', $content);
    }


    public function sendMediaGroup(array $content)
    {
        return $this->endpoint('sendMediaGroup', $content);
    }


    public function sendVenue(array $content)
    {
        return $this->endpoint('sendVenue', $content);
    }


    public function sendContact(array $content)
    {
        return $this->endpoint('sendContact', $content);
    }


    public function sendChatAction(array $content)
    {
        return $this->endpoint('sendChatAction', $content);
    }


    public function getUserProfilePhotos(array $content)
    {
        return $this->endpoint('getUserProfilePhotos', $content);
    }


    private function getFile($file_id): string
    {
        $content = ['file_id' => $file_id];
        return $this->endpoint('getFile', $content)['result']['file_path'];
    }


    public function kickChatMember(array $content)
    {
        return $this->endpoint('kickChatMember', $content);
    }


    public function leaveChat(array $content)
    {
        return $this->endpoint('leaveChat', $content);
    }


    public function unbanChatMember(array $content)
    {
        return $this->endpoint('unbanChatMember', $content);
    }


    public function getChat(array $content)
    {
        return $this->endpoint('getChat', $content);
    }


    public function getChatAdministrators(array $content)
    {
        return $this->endpoint('getChatAdministrators', $content);
    }


    public function getChatMembersCount(array $content)
    {
        return $this->endpoint('getChatMembersCount', $content);
    }


    public function getChatMember(array $content)
    {
        return $this->endpoint('getChatMember', $content);
    }


    public function answerInlineQuery(array $content)
    {
        return $this->endpoint('answerInlineQuery', $content);
    }


    public function setGameScore(array $content)
    {
        return $this->endpoint('setGameScore', $content);
    }


    public function answerCallbackQuery(array $content)
    {
        return $this->endpoint('answerCallbackQuery', $content);
    }


    /**
     * @param int|null $chat_id
     * @param int|null $message_id
     * @param string $text
     * @param string|null $parse_mode
     * @param bool $disable_web_page_preview
     * @param int|null $reply_to_message_id
     * @param null $reply_markup
     * 
     * @return array
     */
    public function editMessageText(
        string $text,
        ?string $parse_mode = NULL,
        bool $disable_web_page_preview = false,
        ?int $reply_to_message_id = NULL,
        ?int $message_id = NULL,
        ?int $chat_id = NULL,
        $reply_markup = null
    ): array {
        $content = [
            'chat_id' => $chat_id ?? $this->getUserId(),
            'message_id' => $message_id ?? $this->MessageID(),
            'text' => $text,
            'parse_mode' => $parse_mode ?? $this->mode,
            'disable_web_page_preview' => $disable_web_page_preview,
        ];

        if (!is_null($reply_to_message_id))
            $content['reply_to_message_id'] = $reply_to_message_id;

        if (!is_null($reply_markup))
            $content['reply_markup'] = $reply_markup;

        return $this->endpoint('editMessageText', $content);
    }


    public function editMessageCaption(array $content)
    {
        return $this->endpoint('editMessageCaption', $content);
    }


    public function editMessageReplyMarkup(array $content)
    {
        return $this->endpoint('editMessageReplyMarkup', $content);
    }


    public function downloadFile(string $file_id, string $local_path): void
    {

        $file_url = 'https://api.telegram.org/file/bot' . $this->bot_token . '/' . $this->getFile($file_id);
        $response = Http::get($file_url);
        $photo_data = $response->body();
        Storage::put($local_path, $photo_data);
    }


    public function setWebhook(string $url, string $certificate = '')
    {
        if ($certificate == '')
            $requestBody = ['url' => $url];
        else
            $requestBody = ['url' => $url, 'certificate' => @$certificate];

        return $this->endpoint('setWebhook', $requestBody, true);
    }


    public function getData()
    {
        if (empty(self::$data))
            return json_decode(file_get_contents('php://input'), true);
        else
            return self::$data;
    }


    public static function setData(array $data): void
    {
        self::$data = $data;
    }

    public function Text(): string
    {
        $type = $this->getUpdateType();
        if ($type == self::CALLBACK_QUERY)
            return @self::$data['callback_query']['message']['text'];
        elseif ($type == self::CHANNEL_POST)
            return @self::$data['channel_post']['text'];
        elseif ($type == self::EDITED_MESSAGE)
            return @self::$data['edited_message']['text'];
        elseif ($type == self::MESSAGE)
            return @self::$data['message']['text'];
        else
            return '';
    }


    public function InlineQuery(): string
    {
        return self::$data['inline_query']['query'];
    }


    public function PhotoId(): string
    {
        $type = $this->getUpdateType();
        @$photo_test = @self::$data['message']['photo'] ?? [];

        $count = count($photo_test) - 1;
        if ($type == self::PHOTO and !empty($count))
            return @self::$data['message']['photo'][$count]['file_id'];
        else
            return 'no photo';
    }


    public function VideoId(): string
    {
        $type = $this->getUpdateType();
        if ($type == self::VIDEO)
            return @self::$data['message']['video']['file_id'];
        else
            return 'no video';
    }


    public function documentId(): string
    {
        $type = $this->getUpdateType();
        if ($type == self::DOCUMENT)
            return @self::$data['message']['document']['file_id'];
        else
            return 'no document';
    }


    public function Caption(): ?string
    {
        $type = $this->getUpdateType();
        if ($type == self::CHANNEL_POST)
            return @self::$data['channel_post']['caption'];
        else
            return @self::$data['message']['caption'];
    }


    public function ChatID(): string
    {
        $type = $this->getUpdateType();
        if ($type == self::CALLBACK_QUERY)
            return @self::$data['callback_query']['message']['chat']['id'];
        elseif ($type == self::CHANNEL_POST)
            return @self::$data['channel_post']['chat']['id'];
        elseif ($type == self::EDITED_MESSAGE)
            return @self::$data['edited_message']['chat']['id'];
        elseif ($type == self::INLINE_QUERY)
            return @self::$data['inline_query']['from']['id'];
        else
            return self::$data['message']['chat']['id'];
    }


    public function MessageID(): int
    {
        $type = $this->getUpdateType();
        if ($type == self::CALLBACK_QUERY)
            return @self::$data['callback_query']['message']['message_id'];
        elseif ($type == self::CHANNEL_POST)
            return @self::$data['channel_post']['message_id'];
        elseif ($type == self::EDITED_MESSAGE)
            return @self::$data['edited_message']['message_id'];
        else
            return self::$data['message']['message_id'];
    }


    public function ReplyToMessageID()
    {
        return self::$data['message']['reply_to_message']['message_id'];
    }


    public function ReplyToMessageFromUserID()
    {
        return self::$data['message']['reply_to_message']['forward_from']['id'];
    }


    public function Inline_Query()
    {
        return self::$data['inline_query'];
    }


    public function Callback_Query()
    {
        return self::$data['callback_query'];
    }


    public function Callback_ID()
    {
        return self::$data['callback_query']['id'];
    }


    public function Callback_Data()
    {
        if ($this->getUpdateType() == self::CALLBACK_QUERY)
            return self::$data['callback_query']['data'];
        else
            return '';
    }


    public function Callback_Message()
    {
        if ($this->getUpdateType() == self::CALLBACK_QUERY)
            return self::$data['callback_query']['message'];
        else
            return '';
    }


    public function Callback_ChatID()
    {
        return self::$data['callback_query']['message']['chat']['id'];
    }


    public function Date()
    {
        return self::$data['message']['date'];
    }


    public function FirstName(): string
    {
        $type = $this->getUpdateType();
        if ($type == self::CALLBACK_QUERY)
            return @self::$data['callback_query']['from']['first_name'];
        elseif ($type == self::CHANNEL_POST)
            return @self::$data['channel_post']['from']['first_name'];
        elseif ($type == self::EDITED_MESSAGE)
            return @self::$data['edited_message']['from']['first_name'];
        else
            return @self::$data['message']['from']['first_name'];
    }


    public function LastName(): string
    {
        $type = $this->getUpdateType();
        if ($type == self::CALLBACK_QUERY)
            return @self::$data['callback_query']['from']['last_name'] ?? '';
        elseif ($type == self::CHANNEL_POST)
            return @self::$data['channel_post']['from']['last_name'] ?? '';
        elseif ($type == self::EDITED_MESSAGE)
            return @self::$data['edited_message']['from']['last_name'] ?? '';
        else
            return @self::$data['message']['from']['last_name'] ?? '';
    }


    public function Username(): ?string
    {
        $type = $this->getUpdateType();
        if ($type == self::CALLBACK_QUERY)
            return @self::$data['callback_query']['from']['username'];
        elseif ($type == self::CHANNEL_POST)
            return @self::$data['channel_post']['from']['username'];
        elseif ($type == self::EDITED_MESSAGE)
            return @self::$data['edited_message']['from']['username'];
        else
            return @self::$data['message']['from']['username'];
    }


    public function Location()
    {
        return self::$data['message']['location'];
    }


    public function UpdateID()
    {
        return self::$data['update_id'];
    }


    public function UpdateCount()
    {
        return count($this->updates['result']);
    }


    public function UserID(): string
    {
        $type = $this->getUpdateType();
        if ($type == self::CALLBACK_QUERY)
            return self::$data['callback_query']['from']['id'];
        elseif ($type == self::CHANNEL_POST)
            return self::$data['channel_post']['from']['id'];
        elseif ($type == self::EDITED_MESSAGE)
            return @self::$data['edited_message']['from']['id'];
        else
            return self::$data['message']['from']['id'];
    }


    public function FromID()
    {
        return self::$data['message']['forward_from']['id'];
    }


    public function FromChatID(): mixed
    {
        return self::$data['message']['forward_from_chat']['id'] ?? NULL;
    }


    public function ForwardFromMessageID(): mixed
    {
        return self::$data['message']['forward_from_message_id'] ?? NULL;
    }


    public function messageFromGroup()
    {
        if (self::$data['message']['chat']['type'] == 'private')
            return false;
        else
            return true;
    }


    public function messageFromGroupTitle()
    {
        if (self::$data['message']['chat']['type'] != 'private')
            return self::$data['message']['chat']['title'];
        else
            return '';
    }


    public function buildKeyBoard(array $options, $onetime = false, $resize = false, $selective = true)
    {
        $replyMarkup = [
            'keyboard' => $options,
            'one_time_keyboard' => $onetime,
            'resize_keyboard' => $resize,
            'selective' => $selective,
        ];
        $encodedMarkup = json_encode($replyMarkup, true);
        return $encodedMarkup;
    }


    public function buildInlineKeyBoard(array $options)
    {
        $replyMarkup = [
            'inline_keyboard' => $options,
        ];
        $encodedMarkup = json_encode($replyMarkup, true);
        return $encodedMarkup;
    }


    public function buildInlineKeyboardButton(
        $text,
        $url = '',
        $callback_data = '',
        $switch_inline_query = null,
        $switch_inline_query_current_chat = null,
        $callback_game = '',
        $pay = ''
    ): array {
        $replyMarkup = [
            'text' => $text,
        ];
        if ($url != '')
            $replyMarkup['url'] = $url;
        elseif ($callback_data != '')
            $replyMarkup['callback_data'] = $callback_data;
        elseif (!is_null($switch_inline_query))
            $replyMarkup['switch_inline_query'] = $switch_inline_query;
        elseif (!is_null($switch_inline_query_current_chat))
            $replyMarkup['switch_inline_query_current_chat'] = $switch_inline_query_current_chat;
        elseif ($callback_game != '')
            $replyMarkup['callback_game'] = $callback_game;
        elseif ($pay != '')
            $replyMarkup['pay'] = $pay;

        return $replyMarkup;
    }


    public function buildKeyboardButton($text, $request_contact = false, $request_location = false)
    {
        $replyMarkup = [
            'text' => $text,
            'request_contact' => $request_contact,
            'request_location' => $request_location,
        ];

        return $replyMarkup;
    }


    public function buildKeyBoardHide($selective = true)
    {
        $replyMarkup = [
            'remove_keyboard' => true,
            'selective' => $selective,
        ];
        $encodedMarkup = json_encode($replyMarkup, true);

        return $encodedMarkup;
    }


    public function buildForceReply($selective = true)
    {
        $replyMarkup = [
            'force_reply' => true,
            'selective' => $selective,
        ];
        $encodedMarkup = json_encode($replyMarkup, true);

        return $encodedMarkup;
    }


    public function sendInvoice(array $content)
    {
        return $this->endpoint('sendInvoice', $content);
    }


    public function copyMessage(array $content)
    {
        return $this->endpoint('copyMessage', $content);
    }


    public function answerShippingQuery(array $content)
    {
        return $this->endpoint('answerShippingQuery', $content);
    }


    public function answerPreCheckoutQuery(array $content)
    {
        return $this->endpoint('answerPreCheckoutQuery', $content);
    }


    public function sendVideoNote(array $content)
    {
        return $this->endpoint('sendVideoNote', $content);
    }


    public function restrictChatMember(array $content)
    {
        return $this->endpoint('restrictChatMember', $content);
    }


    public function promoteChatMember(array $content)
    {
        return $this->endpoint('promoteChatMember', $content);
    }


    public function exportChatInviteLink(array $content)
    {
        return $this->endpoint('exportChatInviteLink', $content);
    }


    public function setChatPhoto(array $content)
    {
        return $this->endpoint('setChatPhoto', $content);
    }


    public function deleteChatPhoto(array $content)
    {
        return $this->endpoint('deleteChatPhoto', $content);
    }


    public function setChatTitle(array $content)
    {
        return $this->endpoint('setChatTitle', $content);
    }


    public function setChatDescription(array $content)
    {
        return $this->endpoint('setChatDescription', $content);
    }


    public function pinChatMessage(array $content)
    {
        return $this->endpoint('pinChatMessage', $content);
    }


    public function unpinChatMessage(array $content)
    {
        return $this->endpoint('unpinChatMessage', $content);
    }


    public function getStickerSet(array $content)
    {
        return $this->endpoint('getStickerSet', $content);
    }


    public function uploadStickerFile(array $content)
    {
        return $this->endpoint('uploadStickerFile', $content);
    }


    public function createNewStickerSet(array $content)
    {
        return $this->endpoint('createNewStickerSet', $content);
    }


    public function addStickerToSet(array $content)
    {
        return $this->endpoint('addStickerToSet', $content);
    }


    public function setStickerPositionInSet(array $content)
    {
        return $this->endpoint('setStickerPositionInSet', $content);
    }


    public function deleteStickerFromSet(array $content)
    {
        return $this->endpoint('deleteStickerFromSet', $content);
    }


    /**
     * @param int|null $chat_id
     * @param int|null $message_id
     * @return array
     */
    public function deleteMessage(?int $chat_id = NULL, ?int $message_id = NULL): array
    {
        $content = [
            'message_id' => $message_id ?? $this->MessageID(),
            'chat_id' => $chat_id ?? $this->getUserId(),
        ];

        return $this->endpoint('deleteMessage', $content);
    }


    /**
     * @param string $method
     */
    public function serveUpdate(string $update)
    {
        self::$data = $this->updates['result'][$update];
    }


    /**
     * @return string|bool
     * 
     * @description Get the type of the update
     */
    public function getUpdateType(): string|bool
    {
        $update = self::$data;

        if (isset($update['inline_query']))
            return self::INLINE_QUERY;
        elseif (isset($update['callback_query']))
            return self::CALLBACK_QUERY;
        elseif (isset($update['message']['document']))
            return self::DOCUMENT;
        elseif (isset($update['edited_message']))
            return self::EDITED_MESSAGE;
        elseif (isset($update['message']['text']))
            return self::MESSAGE;
        elseif (isset($update['message']['photo']))
            return self::PHOTO;
        elseif (isset($update['message']['video']))
            return self::VIDEO;
        elseif (isset($update['message']['audio']))
            return self::AUDIO;
        elseif (isset($update['message']['voice']))
            return self::VOICE;
        elseif (isset($update['message']['contact']))
            return self::CONTACT;
        elseif (isset($update['message']['location']))
            return self::LOCATION;
        elseif (isset($update['message']['reply_to_message']))
            return self::REPLY;
        elseif (isset($update['message']['animation']))
            return self::ANIMATION;
        elseif (isset($update['message']['sticker']))
            return self::STICKER;
        elseif (isset($update['channel_post']))
            return self::CHANNEL_POST;
        else
            return false;
    }


    /**
     * @param string $url
     * @param array $content
     * @param bool $post
     * @return array
     * 
     * @description Send API request to Telegram
     */
    private function sendAPIRequest(string $url, array $content, bool $post = true): array
    {
        if (isset($content['chat_id'])) {
            $url = $url . '?chat_id=' . $content['chat_id'];
            unset($content['chat_id']);
        }

        $result = Http::post($url, $content);

        if ($result->successful())
            return $result->json();
        else
            return [];
    }


    /**
     * @param array|string $param
     * @return bool
     * @description Check if the parameter exists and is not empty
     */
    private function hasParam(array|string $param): bool
    {
        if (is_array($param)) {
            foreach ($param as $p) {
                if (!isset(self::$data[$p]) && empty(self::$data[$p]))
                    return false;
            }
            return true;
        } else {
            return (isset(self::$data[$param]) && !empty(self::$data[$param]));
        }
    }
}
