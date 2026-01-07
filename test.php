
public function handle(Request $request)
{
    $update = $request->all();

    Log::debug('RAW TELEGRAM UPDATE', $update);

    /* ===============================
     | INLINE BUTTON CALLBACK
     ===============================*/
    if (isset($update['callback_query'])) {

        $callback = $update['callback_query'];
        $chatId   = $callback['message']['chat']['id'];
        $telegramId = $callback['from']['id'];
        $data     = $callback['data'];

        $user = TelegramUser::firstOrCreate(
            ['telegram_id' => $telegramId],
            ['state' => 'waiting_city']
        );

        if ($data === 'restart') {
            $this->fsm->reset($user);

            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "🔄 Почнемо спочатку.\n\n🌍 Напиши місто:",
            ]);
        }

        return response()->json(['ok' => true]);
    }

    /* ===============================
     | TEXT MESSAGE
     ===============================*/
    if (!isset($update['message']['text'])) {
        return response()->json(['ok' => true]);
    }

    $text = trim($update['message']['text']);
    $chatId = $update['message']['chat']['id'];
    $telegramId = $update['message']['from']['id'];

    Log::debug('TEXT RECEIVED', ['text' => $text]);

    $user = TelegramUser::firstOrCreate(
        ['telegram_id' => $telegramId],
        ['state' => 'waiting_city']
    );

    Log::debug('USER BEFORE LOGIC', $user->toArray());

    /* ===============================
     | /start
     ===============================*/
    if ($text === '/start') {
        $this->fsm->reset($user);

        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "👋 Привіт!\n\n🌍 Напиши місто:",
        ]);

        return response()->json(['ok' => true]);
    }

    /* ===============================
     | FSM LOGIC
     ===============================*/
    switch ($user->state) {

        case 'waiting_city':
            $this->fsm->handleCity($user, $text);

            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "📅 Введи дату (YYYY-MM-DD)",
            ]);
            break;

        case 'waiting_date':
            $this->fsm->handleDate($user, $text);

            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "⏰ Введи проміжок часу (09:00-18:00)",
            ]);
            break;

        case 'waiting_time':
            $this->fsm->handleTime($user, $text);

            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' =>
                    "✅ Дані отримано!\n\n" .
                    "🌍 Місто: {$user->location}\n" .
                    "📅 Дата: {$user->date}\n" .
                    "⏰ Час: {$text}",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            [
                                'text' => '🔄 Почати заново',
                                'callback_data' => 'restart',
                            ]
                        ]
                    ]
                ]
            ]);
            break;
        case 'done':
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "ℹ️ Запит вже завершено.\n\nНатисни кнопку нижче, щоб почати знову 👇",
                'reply_markup' => [
                    'inline_keyboard' => [
                        [
                            [
                                'text' => '🔄 Почати заново',
                                'callback_data' => 'restart',
                            ]
                        ]
                    ]
                ]
            ]);
            break;

    }
