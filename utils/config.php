<?php


define('TELEGRAM_BOT_TOKEN', getenv("TELEGRAM_BOT_TOKEN"));
const TELEGRAM_API_BOT_URL = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/';

define('DB_DSN', getenv("DB_DSN"));
define('DB_USER', getenv("DB_USER"));
define('DB_PASSWORD', getenv("DB_PASSWORD"));

define('DATA_API_TOKEN', getenv("DATA_API_TOKEN"));

const UPDATE_TYPE_MESSAGE = 'message';
const UPDATE_TYPE_CALLBACK_QUERY = 'callback_query';
const UPDATE_TYPE_INLINE_QUERY = 'inline_query';

const FILMS_PER_PAGE_COUNT = 10;

const COMMAND_START = '/start';
const COMMAND_HELP = '/help';
const COMMAND_GET_RANDOM_FILM = '/get_random_film';
const COMMAND_SET_FILTER = '/set_filter';
const COMMAND_SET_FILTER_GENRE = '/set_filter_genre';
const COMMAND_SET_FILTER_RATING_MIN = '/set_filter_rating_min';
const COMMAND_SET_FILTER_RATING_MAX = '/set_filter_rating_max';
const COMMAND_GET_FILM = '/get_film';
const COMMAND_SEE_NOTEBOOK = '/see_notebook';
const COMMAND_ADD_IN_NOTEBOOK = '/add_in_notebook';
const COMMAND_DELETE_FROM_NOTEBOOK = '/delete_from_notebook';
const COMMAND_CLOSE_NOTEBOOK = '/close_notebook';
const COMMAND_SUBSCRIBE = '/subscribe';
const COMMAND_UNSUBSCRIBE = '/unsubscribe';
