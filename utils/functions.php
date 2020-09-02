<?php

function getChatData(): ?array
{
    $chatData = null;
    $update = json_decode(file_get_contents('php://input'), JSON_OBJECT_AS_ARRAY);
    if ($update[UPDATE_TYPE_MESSAGE])
    {
        $chatData['chatId'] = $update[UPDATE_TYPE_MESSAGE]['chat']['id'];
        $chatData['text'] = $update[UPDATE_TYPE_MESSAGE]['text'];
        $chatData['messageId'] = $update[UPDATE_TYPE_MESSAGE]['message_id'];    
        $chatData['typeMessage'] = UPDATE_TYPE_MESSAGE;
    }
    elseif ($update[UPDATE_TYPE_CALLBACK_QUERY])
    {
        $chatData['chatId'] = $update[UPDATE_TYPE_CALLBACK_QUERY]['message']['chat']['id'];
        $chatData['text'] = $update[UPDATE_TYPE_CALLBACK_QUERY]['data'];
        $chatData['messageId'] = $update[UPDATE_TYPE_CALLBACK_QUERY]['message']['message_id'];    
        $chatData['typeMessage'] = UPDATE_TYPE_CALLBACK_QUERY;
    }
    elseif ($update[UPDATE_TYPE_INLINE_QUERY])
    {
        $chatData['queryId'] = $update[UPDATE_TYPE_INLINE_QUERY]['id'];
        $chatData['chatId'] = $update[UPDATE_TYPE_INLINE_QUERY]['from']['id'];
        $chatData['typeMessage'] = UPDATE_TYPE_INLINE_QUERY;
    }
    return $chatData;
}

function execute(): bool
{
    $data = getChatData();
    if (!$data)
    {
        return false;
    }
    return execMessage($data) || execCallback($data) || execInline($data);
}

function execMessage(array $chatData): bool
{
    if ($chatData['typeMessage'] != UPDATE_TYPE_MESSAGE)
    {
        return false;
    }
    ['chatId' => $chatId, 'text' => $command] = $chatData;
    switch($command)
    {
        case COMMAND_HELP:
            commandHelp($chatId);
            break;
        case COMMAND_START:
            commandStart($chatId);
            break;
        case COMMAND_SET_FILTER:
            commandSetFilter($chatId);
            break;
        default:
            commandSearch($chatId, $command);
    }
    return true;
}

function execCallback(array $chatData): bool
{
    if ($chatData['typeMessage'] != UPDATE_TYPE_CALLBACK_QUERY)
    {
        return false;
    }
    ['chatId' => $chatId, 'text' => $text, 'messageId' => $messageId] = $chatData;
    ['command' => $command, 'param1' => $param1] = getParams($text);
    if (!$command)
    {
        return false;
    }
    switch($command)
    {
        case COMMAND_HELP:
            commandHelp($chatId);
            break;       
        case COMMAND_GET_RANDOM_FILM:
            commandGetRandomFilm($chatId);
            break;
        case COMMAND_SET_FILTER:
            commandSetFilter($chatId);
            break;
        case COMMAND_SET_FILTER_GENRE:
            if (isset($param1))
            {
                commandSetFilterGenre($chatId, $param1, $messageId);     
            }
            break;
        case COMMAND_SET_FILTER_RATING_MAX:
            commandSetFilterRatingMax($chatId, $param1, $messageId);     
            break;
        case COMMAND_SET_FILTER_RATING_MIN:
            commandSetFilterRatingMin($chatId, $param1, $messageId);     
            break;
        case COMMAND_SEE_NOTEBOOK:
            if (isset($param1))
            {
                commandSeeNotebook($chatId, $param1, $messageId);
            }
            else
            {
                commandSeeNotebook($chatId, 1);
            }
            break;
        case COMMAND_ADD_IN_NOTEBOOK:
            if (isset($param1))
            {
                commandAddInNotebook($chatId, $param1);
            }
            break;
        case COMMAND_DELETE_FROM_NOTEBOOK:
            if (isset($param1))
            {
                commandDeleteFromNotebook($chatId, $param1);
            }
            break;
        case COMMAND_CLOSE_NOTEBOOK:
            commandCloseNotebook($chatId, $messageId);
            break;
        case COMMAND_SUBSCRIBE:
            if (isset($param1))
            {
                commandSubscribeAt($chatId, $param1);
            }
            else
            {
                commandSubscribe($chatId);
            }
            break;
        case COMMAND_UNSUBSCRIBE:
            commandUnsubscribe($chatId);
            break;
        case COMMAND_GET_FILM:
            if (isset($param1))
            {
                commandGetFilm($chatId, $param1);
            }
            break;
    }
    return true;    
}

function execInline(array $chatData): bool
{
    if ($chatData['typeMessage'] != UPDATE_TYPE_INLINE_QUERY)
    {
        return false;
    }
    return true;    
}

function database(): PDO
{
    static $connection = null;
    if ($connection === null)
    {
        $connection = new PDO(DB_DSN, DB_USER, DB_PASSWORD);
        $connection->query('SET NAMES utf8');
    }
    return $connection;
}

function commandStart(int $chatId): void
{
    sendStartMessage($chatId);
}

function commandHelp(int $chatId): void
{
    sendHelpMessage($chatId);
}

function commandGetRandomFilm(int $chatId): void
{
    if (!isSetFilter($chatId))
    {
        commandSetFilter($chatId);
        return;
    }
    
    $data = getFilterAnswer($chatId);
    if ($data['answer']['status'] < 0)
    {
        sendInvalidFilterMessage($chatId, $data['other']['genre'], $data['filter']['ratingFrom'], $data['filter']['ratingTo']);
        return;
    }

    $params = $data['filter'];
    $countPages = $data['answer']['pagesCount'];
    $params['page'] = rand(1, $countPages);
    $data = sendRequestDataAPI("https://kinopoiskapiunofficial.tech/api/v2.1/films/search-by-filters?". http_build_query($params));
    if ($data)
    {
        $countFilms = count($data['films']);
        $randomIndex = rand(0, $countFilms - 1);    //API возвращает максимум 20 фильмов на странице
        $randomFilmId = $data['films'][$randomIndex]['filmId'];
        commandGetFilm($chatId, $randomFilmId);
    }
}

function commandSearch(int $chatId, string $text): void
{
    if ($text)
    {
        $data = getListFilmsByKeyword($text);
        if ($data['films'])
        {   
            $rows = [];
            foreach ($data['films'] as $film)
            {
                $name = $film['nameRu'] ?? $film['nameEn'];
                $rows[] = setKeyboardRow(setInlineKeyboardButton("$name ({$film['year']})", COMMAND_GET_FILM . " {$film['filmId']}"));
            }
            sendSearchFilmResultMessage($chatId, $rows);
        } 
    }
}

function commandSetFilter(int $chatId): void
{
    sendSetFilterMessage($chatId);
}

function commandSubscribe(int $chatId): void
{
    sendSubscribeMessage($chatId);
}

function commandSubscribeAt(int $chatId, int $hour): void
{
    $sth = database()->prepare('SELECT COUNT(*) FROM subscribers WHERE chat_id = :chat_id');
    if (!$sth->execute([':chat_id' => $chatId]))
    {
        return;
    }
    if ($sth->fetchColumn() == 0)
    {
        $sth = database()->prepare('INSERT INTO subscribers (chat_id, time_everyday_repeat) VALUES (:chat_id, :hour)');
    }
    else
    {
        $sth = database()->prepare('UPDATE subscribers SET time_everyday_repeat = :hour WHERE chat_id = :chat_id');
    }
    $sth->execute([':chat_id' => $chatId, ':hour' => "$hour:00"]);
    sendSubscribeAtMessage($chatId);
}

function commandUnsubscribe(int $chatId): void
{
    $sth = database()->prepare('DELETE FROM subscribers WHERE chat_id = :chat_id');
    if ($sth->execute([':chat_id' => $chatId]))
    {
        sendUnsubscribeMessage($chatId);
    }
}

function commandDeleteFromNotebook(int $chatId, int $filmId): void
{
    if (!isFilmInNotebook($chatId, $filmId))
    {
        return;
    }
    $sth = database()->prepare('DELETE FROM notebook WHERE chat_id = :chat_id and film_id = :film_id');
    if ($sth->execute([':chat_id' => $chatId, ':film_id' => $filmId]))
    {
        sendDeleteFromNotebookMessage($chatId);
    }
 }

function commandCloseNotebook(int $chatId, int $messageId): void
{
    sendCloseNotebookMessage($chatId, $messageId);
}

function commandAddInNotebook(int $chatId, int $filmId): void
{
    if (isFilmInNotebook($chatId, $filmId))
    {
        return;
    }
    $sth = database()->prepare('INSERT INTO notebook (chat_id, film_id, change_date) VALUES (:chat_id, :film_id, NOW())');
    if (!$sth->execute([':chat_id' => $chatId, ':film_id' => $filmId]))
    {
        return;
    }
    $sth = database()->prepare('SELECT COUNT(*) FROM films WHERE id = :filmId');
    if (!$sth->execute([':filmId' => $filmId]))
    {
        return;
    }
    if ($sth->fetchColumn() == 0)
    {
        ['filmName' => $filmName] = previewFilmInfoById($filmId);
        $sth = database()->prepare('INSERT INTO films (id, name) VALUES (:filmId, :filmName)');
        $sth->execute([':filmId' => $filmId, ':filmName' => $filmName]);
    }
    sendAddInNotebookMessage($chatId);
}

function commandSeeNotebook(string $chatId, int $page, int $messageId = null): void
{
    $message = 'Блокнот пуст. Для его заполнения под фильмом нажимай кнопку «📒 Записать».';
    $params = [];
    $methodName = 'sendMessage';

    $filmsCount = getFilmsInNotebookCount($chatId);
    if ($filmsCount != 0)
    {
        $data = getPageOfNotebook($chatId, $page);
        if ($data)
        {
            $rows = [];
            foreach ($data as $elem)
            {
                $rows[] = setKeyboardRow(setInlineKeyboardButton($elem['name'], COMMAND_GET_FILM . " {$elem['film_id']}"));
            }
            $rows[] = getNotebookNavigationButtons($filmsCount, $page);
            $replyMarkup = setInlineKeyboardMarkup(...$rows);
            $message = 'Блокнот:';
            $params['reply_markup'] = $replyMarkup;
        }
    }
    $params['chat_id'] = $chatId;
    $params['text'] = $message;
    if (!empty($messageId))
    {
        $params['message_id'] = $messageId;
        $methodName = 'editMessageText';
    }
    sendRequestTelegram($methodName, $params);
}

function commandGetFilm(int $chatId, int $filmId): void
{
    ['message' => $message, 'posterURL' => $photo, 'filmName' => $filmName] = previewFilmInfoById($filmId);
    $paramsMessage = ['chat_id' => $chatId, 'text' => $message, 'parse_mode' => 'html', 'disable_web_page_preview' => true];
    $paramsPhoto = ['chat_id' => $chatId, 'photo' => $photo, 'caption' => "Постер фильма «{$filmName}»", 'disable_notification' => true];
    
    $but00 = (isFilmInNotebook($chatId, $filmId)) ? 
        setInlineKeyboardButton('📒 Вычеркнуть', COMMAND_DELETE_FROM_NOTEBOOK . " $filmId") :
        setInlineKeyboardButton('📒 Записать', COMMAND_ADD_IN_NOTEBOOK . " $filmId");
    $but01 = setInlineKeyboardButton('🍿 Посоветуй', COMMAND_GET_RANDOM_FILM);
    $replyMarkup = setInlineKeyboardMarkup(setKeyboardRow($but00, $but01));
    
    if (empty($photo))
    {
        $paramsMessage['reply_markup'] = $replyMarkup;
    } 
    else
    {
        $paramsPhoto['reply_markup'] = $replyMarkup;
    }
    sendRequestTelegram('sendMessage', $paramsMessage);
            
    if (!empty($photo))
    {
        sendRequestTelegram('sendPhoto', $paramsPhoto);
    }
}

/*----------------------------------------------------
----------------------СООБЩЕНИЯ-----------------------
----------------------------------------------------*/

function sendInvalidFilterMessage(int $chatId, string $genre, int $minRating, int $maxRating): void
{
    $message = "Я не смог найти фильм жанра <b>$genre</b> с ретингом <b>от $minRating до $maxRating</b>.";
    $but00 = setInlineKeyboardButton('💥 Изменить предпочтения', COMMAND_SET_FILTER);
    $but01 = setInlineKeyboardButton('🍿 Искать дальше', COMMAND_GET_RANDOM_FILM);
    $replyMarkup = setInlineKeyboardMarkup(setKeyboardRow($but00, $but01));
    sendRequestTelegram('sendMessage', ['chat_id' => $chatId, 'text' => $message, 'parse_mode' => 'html', 'reply_markup' => $replyMarkup]);  
}

function sendHelpMessage(int $chatId): void
{
    $message = "• Напиши название фильма в строке, и я расскажу про него все, что знаю." . PHP_EOL .
               "• На основе твоих предпочтений порекомендую фильм." . PHP_EOL .
               "• Если описание фильма тебе понравилось, запишу название в блокнот, чтобы ты не забыл." . PHP_EOL .
               "• В удобный для тебя час напомню, что ты хотел посмотреть." . PHP_EOL . PHP_EOL .
               "Я всегда здесь, жду как Хатико. Ruff.";

    $but00 = setInlineKeyboardButton('🍿 Посоветуй фильм', COMMAND_GET_RANDOM_FILM);
    $but30 = setInlineKeyboardButton('💥 Указать предпочтения', COMMAND_SET_FILTER);
    $but10 = setInlineKeyboardButton('📒 Открыть блокнот', COMMAND_SEE_NOTEBOOK);
    $but20 = setInlineKeyboardButton('🔔 Напомни мне посмотреть фильм позже', COMMAND_SUBSCRIBE);
    $replyMarkup = setInlineKeyboardMarkup(setKeyboardRow($but00), setKeyboardRow($but30), setKeyboardRow($but10), setKeyboardRow($but20));
    sendRequestTelegram('sendMessage', ['chat_id' => $chatId, 'text' => $message, 'reply_markup' => $replyMarkup]);
}

function sendStartMessage(int $chatId): void
{
    $message = "Woof woof. Привет друг! Как я рад тебя видеть! Помочь тебе найти кино на вечер? У меня хороший нюх.";
    $but00 = setInlineKeyboardButton('🍿 Посоветуй фильм', COMMAND_GET_RANDOM_FILM);
    $but01 = setInlineKeyboardButton('❓ Что ты еще умеешь?', COMMAND_HELP);
    $replyMarkup = setInlineKeyboardMarkup(setKeyboardRow($but00, $but01));
    sendRequestTelegram('sendMessage', ['chat_id' => $chatId, 'text' => $message, 'reply_markup' => $replyMarkup]);   
}

function sendSearchFilmResultMessage(int $chatId, array $rowsButtons): void
{
    $message = 'Это все, что я смог откопать. Про какой фильм ты хочешь, чтобы я тебе рассказал?';
    $replyMarkup = setInlineKeyboardMarkup(...$rowsButtons);
    sendRequestTelegram('sendMessage', ['chat_id' => $chatId, 'text' => $message, 'reply_markup' => $replyMarkup]);
}

function sendSetFilterMessage(int $chatId): void
{
    //изменяемое сообщение
    $message = 'Расскажи о своих предпочтениях. Это поможет мне порекомендовать хорошее кино.' . PHP_EOL . 
               'Фильмы каких жанров ты смотришь?' . PHP_EOL . PHP_EOL . 
               getFilterText($chatId);
    $replyMarkup = getGenresKeyboard($chatId);
    sendRequestTelegram('sendMessage', ['chat_id' => $chatId, 'text' => $message, 'parse_mode' => 'html', 'reply_markup' => $replyMarkup]);  
}

function sendSubscribeMessage(int $chatId): void
{
    $message = 'Выбери время, в которое я ежедневно буду напоминать посмотреть случайный фильм из блокнота.';
    $but00 = setInlineKeyboardButton('17:00', COMMAND_SUBSCRIBE . " 17");
    $but01 = setInlineKeyboardButton('19:00', COMMAND_SUBSCRIBE . " 19");
    $but02 = setInlineKeyboardButton('21:00', COMMAND_SUBSCRIBE . " 21");
    $but10 = setInlineKeyboardButton('🔕 Убрать напоминание', COMMAND_UNSUBSCRIBE);
    $replyMarkup = setInlineKeyboardMarkup(setKeyboardRow($but00, $but01, $but02), setKeyboardRow($but10));
    sendRequestTelegram('sendMessage', ['chat_id' => $chatId, 'text' => $message, 'reply_markup' => $replyMarkup]);
}

function sendSubscribeAtMessage(int $chatId): void
{
    $message = 'Запомнил! В указанный час я напомню о фильме, который ты хотел посмотреть.';
    sendRequestTelegram('sendMessage', ['chat_id' => $chatId, 'text' => $message]);
}

function sendUnsubscribeMessage(int $chatId): void
{
    $message = 'Подписка на напоминания отменена.';
    sendRequestTelegram('sendMessage', ['chat_id' => $chatId, 'text' => $message]);
}

function sendDeleteFromNotebookMessage(int $chatId): void
{
    //изменяемое сообщение
    $message = 'Фильм вычеркнут из блокнота.';
    $page = 1;
    $but00 = setInlineKeyboardButton('📒 Открыть блокнот', COMMAND_SEE_NOTEBOOK . " $page");
    $replyMarkup = setInlineKeyboardMarkup(setKeyboardRow($but00));
    sendRequestTelegram('sendMessage', ['chat_id' => $chatId, 'text' => $message, 'reply_markup' => $replyMarkup]); 
}

function sendAddInNotebookMessage(int $chatId): void
{
    $message = 'Фильм записан в блокнот.';
    $page = 1;
    $but00 = setInlineKeyboardButton('📒 Открыть блокнот', COMMAND_SEE_NOTEBOOK . " $page");
    $replyMarkup = setInlineKeyboardMarkup(setKeyboardRow($but00));
    sendRequestTelegram('sendMessage', ['chat_id' => $chatId, 'text' => $message, 'reply_markup' => $replyMarkup]);
}

function sendCloseNotebookMessage(int $chatId, int $messageId): void
{
    $message = 'Блокнот закрыт.';
    $page = 1; 
    $but00 = setInlineKeyboardButton('📒 Открыть блокнот', COMMAND_SEE_NOTEBOOK . " $page");
    $replyMarkup = setInlineKeyboardMarkup(setKeyboardRow($but00));
    sendRequestTelegram('editMessageText', ['chat_id' => $chatId, 'text' => $message, 'message_id' => $messageId, 'reply_markup' => $replyMarkup]);    
}

function sendTimeForCinema(int $chatId): void
{
    $message = 'Настало время для фильма! Сделай перерыв и посмотри хорошее кино. Сегодня у нас это будет ...';
    sendRequestTelegram('sendMessage', ['chat_id' => $chatId, 'text' => $message]);
}   

/*----------------------------------------------------
---------------ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ----------------
----------------------------------------------------*/

function getFilmInfoById(int $id): ?array
{
    $result = null;
    $data = sendRequestDataAPI("https://kinopoiskapiunofficial.tech/api/v2.1/films/$id?append_to_response=RATING"); 
    if ($data)
    {
        $result = $data['data'];
        foreach ($data['rating'] as $key => $value)
        {
            $result[$key] = $value;
        }
    }
    return $result;
}

function getListFilmsByKeyword(string $keyword): ?array
{
    $params['keyword'] = $keyword;
    $params['page'] = 1;
    return sendRequestDataAPI("https://kinopoiskapiunofficial.tech/api/v2.1/films/search-by-keyword?" . http_build_query($params));
}

function getValueForCommand(string $command, string $inputText): ?int
{
    $result = null;
    if (preg_match("/^\\$command [+-]?\d+$/", $inputText))
    {
        if (preg_match("/[+-]?\d+$/", $inputText, $matches))
        {
            $result = $matches[0];
        }
    }
    return $result;
}

function getChatFilterGenres(int $chatId): ?array
{   
    $chatGenres = null;
    $sth = database()->prepare('SELECT b.genre, a.genre_id FROM filter_genre a JOIN genres b ON a.genre_id = b.id WHERE chat_id = :chat_id');
    $sth->execute([':chat_id' => $chatId]);
    $data = $sth->fetchAll();
    if ($data)
    {
        foreach($data as $value)
        {
            $chatGenres[$value['genre_id']] = $value['genre'];
        }
    }
    return $chatGenres;
}

function getChatFilterRandomGenre(int $chatId): ?array
{
    $genres = getChatFilterGenres($chatId);
    if (!$genres)
    {
        return null;
    }
    $chatGenreIds = array_keys($genres);
    $chatGenreIdsCount = count($chatGenreIds);
    $chatGenreIdsRandomIndex = rand(0, $chatGenreIdsCount - 1);
    $randomGenreId = $chatGenreIds[$chatGenreIdsRandomIndex];
    return ['id' => $randomGenreId, 'genre' => $genres[$randomGenreId]];
}

function getChatFilterRatings(int $chatId): array
{
    $ratings = ['min_rating' => 0, 'max_rating' => 10];
    $sth = database()->prepare('SELECT min_rating, max_rating FROM filter_rating WHERE chat_id = :chat_id');
    $sth->execute([':chat_id' => $chatId]);
    $data = $sth->fetch();
    if ($data)
    {
        $ratings['min_rating'] = isset($data['min_rating']) ? $data['min_rating'] : 0;
        $ratings['max_rating'] = isset($data['max_rating']) ? $data['max_rating'] : 10;
    }
    return $ratings;    
}

function getFilterText(int $chatId): string
{
    $chatGenres = getChatFilterGenres($chatId);
    $chatRatings = getChatFilterRatings($chatId);
    $message = "<b>Жанры:</b> ";
    if ($chatGenres)
    {
        foreach($chatGenres as $value)
        {
            $message .= "$value ";    
        }  
    }
    else
    {
        $message .= "все ";
    }
    $message .= PHP_EOL . "<b>Рейтинг:</b> от {$chatRatings['min_rating']} до {$chatRatings['max_rating']} ";
    return $message;
}

function getFilterAnswer(int $chatId): array
{
    ['id' => $genreId, 'genre' => $genre] = getChatFilterRandomGenre($chatId);
    if ($genreId)
    {   
        $params['genre'] = $genreId; 
    }
    $params['order'] = 'RATING'; 
    ['min_rating' => $minRating, 'max_rating' => $maxRating] = getChatFilterRatings($chatId);
    $params['ratingFrom'] = $minRating;
    $params['ratingTo'] = $maxRating;
    $params['yearFrom'] = 1888;
    $params['yearTo'] = date('Y');
    $params['page'] = 1;
    $genre = $genre ?? 'все';
    if ($minRating > $maxRating)
    {
        return ['filter' => $params, 'answer' => ['status' => -2], 'other' => ['genre' => $genre]];
    }
    $data = sendRequestDataAPI("https://kinopoiskapiunofficial.tech/api/v2.1/films/search-by-filters?". http_build_query($params));
    if ($data)
    {
        return ['filter' => $params, 'answer' => ['status' => 1, 'pagesCount' => $data["pagesCount"]], 'other' => ['genre' => $genre]];
    }
    else
    {
        return ['filter' => $params, 'answer' => ['status' => -1], 'other' => ['genre' => $genre]];
    }
}

function getGenres(): ?array
{
    $data = sendRequestDataAPI("https://kinopoiskapiunofficial.tech/api/v2.1/films/filters"); 
    return $data['genres'];
}

function isSetFilter(int $chatId): bool
{
    $sth = database()->prepare('SELECT COUNT(*) FROM filter_rating WHERE chat_id = :chat_id');
    $sth->execute([':chat_id' => $chatId]);
    return ($sth->fetchColumn() != 0);
}

function previewFilmInfoById(int $filmId): array
{
    $message = "Фильм не найден.";
    $posterURL = null;
    $filmName = "Фильм не найден.";
 
    $filmInfo = getFilmInfoById($filmId);
    if ($filmInfo)
    {
        $filmName = (empty($filmInfo['nameRu']) ? $filmInfo['nameEn'] : $filmInfo['nameRu']);
        $message = "<b>" . $filmName . "</b>";
        if (!empty($filmInfo['nameRu']) && !empty($filmInfo['nameEn']))
        {
            $message .= PHP_EOL . $filmInfo['nameEn'];
        }
        $message .= PHP_EOL . $filmInfo['year'];
        $message .= PHP_EOL . PHP_EOL . "Сюжет:" . PHP_EOL . ($filmInfo['description'] ?? "...");
        $message .= PHP_EOL . PHP_EOL . "Рейтинг: {$filmInfo['rating']}" . PHP_EOL . "Жанр: ";
        foreach ($filmInfo['genres'] as $value)
        {
            $message .= "{$value['genre']} ";
        }
        $message .= '...';
        $message .= PHP_EOL . "<a href='{$filmInfo['webUrl']}'>Кинопоиск</a>";
        $posterURL = $filmInfo['posterUrlPreview'];
    }
    $result['message'] = $message;
    $result['posterURL'] = $posterURL;
    $result['filmName'] = $filmName;  
    return $result;
}

function getParams(string $text): ?array
{
    $params = null;
    if (preg_match("/^(?<command>\/[_a-z]+)(?<param1>\s[-]?\d+(?<param2>\s\[{1}.+\]{1})?)?$/", $text, $matches))
    {
        $params['command'] = $matches['command'];
        $params['param1'] = (is_null($matches['param1']) ? null : ltrim($matches['param1']));
        $params['param2'] = (is_null($matches['param2']) ? null : substr(ltrim($matches['param2']), 1, -1));    
    }
    return $params;
}

function getGenresKeyboard(string $chatId): string
{
    $genres = getGenres();
    $myGenres = getChatFilterGenres($chatId);
    $rows = [];
    $rows[] = setKeyboardRow(setInlineKeyboardButton('Нет предпочтений (все)', COMMAND_SET_FILTER_GENRE . " -1"));
    $countGenres = 0;
    $row = [];
    foreach($genres as $genre)
    {
        if (array_key_exists($genre['id'], $myGenres))
        {
            $row[] = setInlineKeyboardButton('🔒', '0');
        }
        else
        {
            $row[] = setInlineKeyboardButton($genre['genre'], COMMAND_SET_FILTER_GENRE . " {$genre['id']}");
        }
        $countGenres += 1;
        if (($countGenres % 3) == 0)
        {
            $rows[] = setKeyboardRow(...$row);
            $row = [];    
        }
    }
    if (!empty($row))
    {   
        for ($i = ($countGenres % 3); $i < 3; $i++)
        {
            $row[] = setInlineKeyboardButton('🔒', '0');
        }
        $rows[] = setKeyboardRow(...$row);
    }
    $rows[] = setKeyboardRow(setInlineKeyboardButton('👉🏻 Дальше', COMMAND_SET_FILTER_RATING_MIN));
    return setInlineKeyboardMarkup(...$rows);
}

function getRatingMinKeyboard(): string
{
    $rows[] = setKeyboardRow(setInlineKeyboardButton('Нет предпочтений (0)', COMMAND_SET_FILTER_RATING_MIN . " 0"));
    $row = [];
    for ($i = 1; $i <= 9; $i++)
    {
        $row[] = setInlineKeyboardButton($i, COMMAND_SET_FILTER_RATING_MIN . " $i");
        if (($i % 3) == 0)
        {
            $rows[] = setKeyboardRow(...$row);
            $row = [];    
        }
    }
    $rows[] = setKeyboardRow(setInlineKeyboardButton('👉🏻 Дальше', COMMAND_SET_FILTER_RATING_MAX));
    return setInlineKeyboardMarkup(...$rows);   
}

function commandSetFilterGenre(string $chatId, int $genreId, int $messageId): void
{
    if ($genreId === -1)
    {
        $sth = database()->prepare('DELETE FROM filter_genre WHERE chat_id = :chat_id');
        $sth->execute([':chat_id' => $chatId]);
    }
    else
    {
        $sth = database()->prepare('SELECT COUNT(*) FROM filter_genre WHERE chat_id = :chat_id AND genre_id = :genre_id');
        $sth->execute([':chat_id' => $chatId, ':genre_id' => $genreId]);
        if ($sth->fetchColumn() == 0)
        {
            $sth = database()->prepare('INSERT INTO filter_genre (chat_id, genre_id) VALUES (:chat_id, :genre_id)');
            $sth->execute([':chat_id' => $chatId, ':genre_id' => $genreId]);    
        }
    }
    $message = 'Есть еще любимые жанры?' . PHP_EOL . PHP_EOL . getFilterText($chatId);
    $replyMarkup = getGenresKeyboard($chatId);
    sendRequestTelegram('editMessageText', ['chat_id' => $chatId, 'text' => $message, 'message_id' => $messageId, 'parse_mode' => 'html', 'reply_markup' => $replyMarkup]); 
}

function commandSetFilterRatingMin(string $chatId, ?int $rating, int $messageId): void
{
    if (is_null($rating))
    {
        $message = 'Чтобы фильм, который я порекомендую для просмотра, тебе понравился, расскажи о своих предпочтениях.' . PHP_EOL . 'Укажи минимальный рейтинг фильма.' . PHP_EOL . PHP_EOL . getFilterText($chatId);
        $replyMarkup = getRatingMinKeyboard();
        sendRequestTelegram('editMessageText', ['chat_id' => $chatId, 'text' => $message, 'message_id' => $messageId, 'parse_mode' => 'html', 'reply_markup' => $replyMarkup]);     
    }
    else
    {
        $sth = database()->prepare('SELECT COUNT(*) FROM filter_rating WHERE chat_id = :chat_id');
        $sth->execute([':chat_id' => $chatId]);
        if ($sth->fetchColumn() == 0)
        {
            $sth = database()->prepare('INSERT INTO filter_rating (chat_id, min_rating) VALUES (:chat_id, :rating)');
            $sth->execute([':chat_id' => $chatId, ':rating' => $rating]);    
        }
        else
        {
            $sth = database()->prepare('UPDATE filter_rating SET min_rating = :rating WHERE chat_id = :chat_id');
            $sth->execute([':chat_id' => $chatId, ':rating' => $rating]);    
        }
        commandSetFilterRatingMax($chatId, null, $messageId);       
    }
}

function getRatingMaxKeyboard(string $chatId): string
{
    $rows[] = setKeyboardRow(setInlineKeyboardButton('Нет предпочтений (10)', COMMAND_SET_FILTER_RATING_MAX . " 10"));
    $row = [];
    ['min_rating' => $minRating, 'max_rating' => $maxRating] = getChatFilterRatings($chatId);
    for ($i = 1; $i <= 9; $i++)
    {
        $row[] = ($i <= $minRating) ?
            setInlineKeyboardButton('🔒', '0') :
            setInlineKeyboardButton($i, COMMAND_SET_FILTER_RATING_MAX . " $i");
        if (($i % 3) == 0)
        {
            $rows[] = setKeyboardRow(...$row);
            $row = [];    
        }
    }
    $rows[] = ($minRating > $maxRating) ?
        setKeyboardRow(setInlineKeyboardButton('🔒', '0')) :    
        setKeyboardRow(setInlineKeyboardButton('👉🏻 Дальше', COMMAND_SET_FILTER_RATING_MAX . " -1"));
    return setInlineKeyboardMarkup(...$rows);   
}

function commandSetFilterRatingMax(string $chatId, ?int $rating, int $messageId): void
{
    if (is_null($rating))
    {
        $message = 'Чтобы фильм, который я порекомендую для просмотра, тебе понравился, расскажи о своих предпочтениях.' . PHP_EOL . 'Укажи максимальный рейтинг.' . PHP_EOL . PHP_EOL . getFilterText($chatId);
        $replyMarkup = getRatingMaxKeyboard($chatId);
        sendRequestTelegram('editMessageText', ['chat_id' => $chatId, 'text' => $message, 'message_id' => $messageId, 'parse_mode' => 'html', 'reply_markup' => $replyMarkup]);     
        return;
    }

    if ($rating != -1)
    {
        $sth = database()->prepare('SELECT COUNT(*) FROM filter_rating WHERE chat_id = :chat_id');
        $sth->execute([':chat_id' => $chatId]);
        if ($sth->fetchColumn() == 0)
        {
            $sth = database()->prepare('INSERT INTO filter_rating (chat_id, max_rating) VALUES (:chat_id, :rating)');
            $sth->execute([':chat_id' => $chatId, ':rating' => $rating]);    
        }
        else
        {
            $sth = database()->prepare('UPDATE filter_rating SET max_rating = :rating WHERE chat_id = :chat_id');
            $sth->execute([':chat_id' => $chatId, ':rating' => $rating]);    
        }
    }
    $message = 'Готово!' . PHP_EOL . PHP_EOL . getFilterText($chatId) . PHP_EOL . PHP_EOL . 'Пора взять след хорошего кино.';
    $but00 = setInlineKeyboardButton('🍿 Посоветуй фильм', COMMAND_GET_RANDOM_FILM);
    $replyMarkup = setInlineKeyboardMarkup(setKeyboardRow($but00));
    sendRequestTelegram('editMessageText', ['chat_id' => $chatId, 'text' => $message, 'message_id' => $messageId, 'parse_mode' => 'html', 'reply_markup' => $replyMarkup]); 
}

function getNotebookNavigationButtons(int $filmsCount, int $currentPage): array
{
    $navigationButtons = [];
    $pagesCount = ceil($filmsCount / FILMS_PER_PAGE_COUNT);
    if ($pagesCount == 1)
    {
        $navigationButtons = setKeyboardRow(setInlineKeyboardButton('⛔ Закрыть', COMMAND_CLOSE_NOTEBOOK));    
    }
    else
    {
        if ($currentPage == 1)
        {
            $but00 = setInlineKeyboardButton('⛔ Закрыть', COMMAND_CLOSE_NOTEBOOK);    
        }    
        else
        {
            $previousPage = $currentPage - 1;
            $but00 = setInlineKeyboardButton("👈🏻 Назад ($previousPage/$pagesCount)", COMMAND_SEE_NOTEBOOK . " $previousPage");
        }
        if ($currentPage == $pagesCount)
        {
            $but01 = setInlineKeyboardButton('⛔ Закрыть', COMMAND_CLOSE_NOTEBOOK);    
        }    
        else
        {
            $nextPage = $currentPage + 1;
            $but01 = setInlineKeyboardButton("👉🏻 Вперед ($nextPage/$pagesCount)", COMMAND_SEE_NOTEBOOK . " $nextPage");
        }
        $navigationButtons = setKeyboardRow($but00, $but01);
    }
    return $navigationButtons;
}

/*-----------РАБОТА С API------------*/

function sendRequestTelegram(string $method, array $params = []): ?array
{
    if (!empty($params)) 
    {
        $url = TELEGRAM_API_BOT_URL . $method . '?' . http_build_query($params);
    } 
    else 
    {
       $url = TELEGRAM_API_BOT_URL . $method;
    }
    return json_decode(file_get_contents($url), JSON_OBJECT_AS_ARRAY);
}

function sendRequestDataAPI(string $url): ?array
{
    $http = 
    [
        'http' => 
            [
                'method'  => 'GET',
                'header'  => ['Content-type: application/json', 'x-api-key: ' . DATA_API_TOKEN]
            ]
    ];
    $context = stream_context_create($http);
    $contents = file_get_contents($url, false, $context);
    preg_match("/\d{3}/", $http_response_header[0], $matches);
    if ($matches[0] == 404)
    {
        return null;
    }
    return json_decode($contents, JSON_OBJECT_AS_ARRAY); 
}

/*-----------КЛАВИАТУРА------------*/

function setInlineKeyboardButton(string $caption, string $command): array
{   
    return ['text' => $caption, 'callback_data' => $command];
}

function setKeyboardRow(array ...$keyboardButtons): array
{
    $row = [];
    foreach ($keyboardButtons as $button) 
    {
        $row[] = $button;
    }
    return $row;
}

function setInlineKeyboardMarkup(array ...$keyboardRows): string
{
    $keyboard = [];
    foreach ($keyboardRows as $row) 
    {
        $keyboard[] = $row;
    }
    return json_encode(['inline_keyboard' => $keyboard]); 
}

/*-----------БЛОКНОТ------------*/

function isFilmInNotebook(int $chatId, int $filmId): bool
{
    $sth = database()->prepare('SELECT COUNT(*) FROM notebook WHERE chat_id = :chat_id and film_id = :film_id');
    $sth->execute([':chat_id' => $chatId, ':film_id' => $filmId]);
    return ($sth->fetchColumn() != 0);
}

function getFilmsInNotebookCount(int $chatId): int
{
    $sth = database()->prepare('SELECT COUNT(*) FROM notebook WHERE chat_id = :chat_id');
    $sth->execute([':chat_id' => $chatId]);
    return $sth->fetchColumn();
}

function getPageOfNotebook(int $chatId, int $page): ?array
{
    $startNumber = ($page - 1) * FILMS_PER_PAGE_COUNT;
    $sth = database()->prepare("SELECT a.film_id, b.name FROM notebook a JOIN films b ON a.film_id = b.id WHERE chat_id = :chat_id ORDER BY a.change_date LIMIT " . $startNumber . ", " . FILMS_PER_PAGE_COUNT);
    $sth->execute([':chat_id' => $chatId]);
    $data = $sth->fetchAll();
    if ($data)
    {
        return $data;
    }
    else
    {
        return null;
    }
}


/*----------------------------------------------------
-----------ФУНКЦИИ, ВЫПОЛНЯЕМЫЕ ПО ЗАДАНИЮ------------
----------------------------------------------------*/

function loadGenres(): void
{
    $genres = getGenres();
    if (!$genres)
    {
        return;
    }

    $sth = database()->prepare('DELETE FROM genres');
    if ($sth->execute())
    {
        $query = 'INSERT INTO genres (id, genre) VALUES ';
        foreach($genres as $genre)
        {
            $query .= "({$genre['id']}, '{$genre['genre']}'), ";
        }
        $query = substr($query, 0, -2);
        $sth = database()->prepare($query);
        $sth->execute();
    }      
}

function remindFilmFromNotebook(): void
{
    $sth = database()->prepare('SELECT COUNT(*) FROM subscribers WHERE HOUR(time_everyday_repeat) = HOUR(TIME(DATE_ADD(NOW(), INTERVAL 3 HOUR))) AND chat_id IN (SELECT DISTINCT chat_id FROM notebook)');
    $sth->execute();
    if ($sth->fetchColumn() == 0)
    {
        return;
    }

    $sth = database()->prepare('SELECT a.chat_id, COUNT(*) row_num FROM subscribers a JOIN notebook b ON a.chat_id = b.chat_id AND HOUR(a.time_everyday_repeat) = HOUR(TIME(DATE_ADD(NOW(), INTERVAL 3 HOUR))) GROUP BY a.chat_id');
    $sth->execute();
    $data = $sth->fetchAll();
    if (!$data)
    {
        return;
    }

    foreach ($data as $elem)
    {
        ['chat_id' => $chatId, 'row_num' => $filmCount] = $elem;
        $filmNum = rand(0, $filmCount - 1);
        $sth = database()->prepare("SELECT film_id FROM notebook WHERE chat_id = :chat_id LIMIT $filmNum, 1");
        $sth->execute([':chat_id' => $chatId]);
        $filmId = $sth->fetchColumn();
        if ($filmId)
        {
            sendTimeForCinema($chatId);
            commandGetFilm($chatId, $filmId);
        }
    }
}