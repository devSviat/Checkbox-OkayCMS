<?php

$lang['sviat__checkbox__title'] = 'Checkbox ПРРО';
$lang['sviat__checkbox__settings_saved'] = 'Настройки сохранены';
$lang['sviat__checkbox__settings'] = 'Настройки';
$lang['sviat__checkbox__cashier_credentials'] = 'Учётные данные кассира';
$lang['sviat__checkbox__receipt_settings'] = 'Настройки чеков';
$lang['sviat__checkbox__automation_settings'] = 'Автоматизация';
$lang['sviat__checkbox__login'] = 'Логин';
$lang['sviat__checkbox__login_placeholder'] = 'Введите логин кассира';
$lang['sviat__checkbox__password'] = 'Пароль';
$lang['sviat__checkbox__password_placeholder'] = 'Введите пароль кассира';
$lang['sviat__checkbox__license_key'] = 'Лицензионный ключ кассы';
$lang['sviat__checkbox__license_key_placeholder'] = 'Введите лицензионный ключ кассы';
$lang['sviat__checkbox__receipt_text_placeholder'] = 'Текст, который будет отображаться в нижней части чека (можно использовать {$order_id} для подстановки номера заказа)';
$lang['sviat__checkbox__order_available_status'] = 'Автоматическое формирование и отправка чека при изменении статуса';
$lang['sviat__checkbox__no_status'] = 'Статус не выбран';
$lang['sviat__checkbox__receipt_text'] = 'Текст для чека';
$lang['sviat__checkbox__receipt_text_order_id_description'] = 'Текст будет отображаться в нижней части чека. Для подстановки номера заказа используйте:';
$lang['sviat__checkbox__receipt_text_order_id_label'] = 'Номер заказа';
$lang['sviat__checkbox__create_shift'] = 'Открыть смену кассира';
$lang['sviat__checkbox__close_shift'] = 'Закрыть смену кассира';
$lang['sviat__checkbox__opened_shift'] = 'Смена открыта';
$lang['sviat__checkbox__just_created_shift'] = 'Смена создана, но ещё не открыта';

$lang['sviat__left_checkbox'] = 'Checkbox ПРРО';
$lang['sviat__left_checkbox_settings'] = 'Настройки';
$lang['sviat__left_checkbox_taxes'] = 'Налоговые ставки';
$lang['sviat__left_checkbox_shifts'] = 'Смены';

$lang['sviat__checkbox__refresh'] = 'Проверить смену';
$lang['sviat__checkbox__show_report'] = 'Показать отчёт';
$lang['sviat__checkbox__shifts_no'] = 'Нет закрытых смен';

$lang['sviat__checkbox__shift_opened_at'] = 'Открыта';
$lang['sviat__checkbox__shift_closed_at'] = 'Закрыта';
$lang['sviat__checkbox__shift_status'] = 'Статус';

$lang['sviat__checkbox__shift_status_created'] = 'Создана';
$lang['sviat__checkbox__shift_status_opening'] = 'Открывается';
$lang['sviat__checkbox__shift_status_opened'] = 'Открыта';
$lang['sviat__checkbox__shift_status_closing'] = 'Закрывается';
$lang['sviat__checkbox__shift_status_closed'] = 'Закрыта';

$lang['sviat__checkbox__errors_empty_params'] = 'Данные кассы не заполнены. Работа с кассой невозможна';
$lang['sviat__checkbox__errors_no_shift'] = 'Смена кассира не создана. Работа с кассой невозможна';
$lang['sviat__checkbox__errors_find_order'] = 'Заказ не найден. Попробуйте позже';
$lang['sviat__checkbox__errors_find_purchases'] = 'Товары в заказе не найдены. Попробуйте позже';
$lang['sviat__checkbox__errors_empty_receipts_isset'] = 'Есть необработанные чеки. Создать новый чек невозможно';
$lang['sviat__checkbox__errors_receipt_in_progress'] = 'Чек уже создаётся. Пожалуйста, подождите';
$lang['sviat__checkbox__errors_unknown_source'] = 'Неизвестный источник средств';
$lang['sviat__checkbox__errors_advance_amount'] = 'Сумма аванса должна быть больше нуля и меньше стоимости товаров';
$lang['sviat__checkbox__modal_advance_title'] = "Чек аванса";
$lang['sviat__checkbox__modal_advance_submit'] = "Выбить чек";
$lang['sviat__checkbox__modal_cancel'] = "Отменить";
$lang['sviat__checkbox__modal_confirm_yes'] = "Да, продолжить";
$lang['sviat__checkbox__confirm_create_return'] = "Будет создан чек возврата в налоговой. Фискализацию заказа это отменяет, и отдельной кнопкой вернуть назад нельзя.";
$lang['sviat__checkbox__confirm_return_chain'] = "Вернуть всю оплату по заказу? Будут созданы чеки возврата в налоговой. Отменить это действие отдельной кнопкой нельзя.";
$lang['sviat__checkbox__errors_chain_is_open'] = "Заказ оплачивается частями — закройте его чеком постоплаты, а не чеком продажи";
$lang['sviat__checkbox__errors_advance_not_a_number'] = "Укажите сумму аванса числом, например 500 или 500,50";
$lang['sviat__checkbox__errors_chain_exists'] = "У заказа уже есть внесённый аванс";
$lang['sviat__checkbox__errors_sale_exists'] = "Заказ уже фискализирован чеком продажи";
$lang['sviat__checkbox__errors_order_paid'] = "Заказ уже оплачен полностью — вносить аванс не с чего";
$lang['sviat__checkbox__errors_no_chain'] = "У заказа нет внесённого аванса";
$lang['sviat__checkbox__errors_chain_closed'] = "Оплата по заказу уже закрыта или возвращена";
$lang['sviat__checkbox__errors_after_payment_amount'] = 'Сумма доплаты должна быть больше нуля и не превышать остаток долга';

$lang['sviat__checkbox__order_receipts'] = 'Чеки заказа';
$lang['sviat__checkbox__order_receipt_create'] = 'Фискализировать чек';
$lang['sviat__checkbox__order_receipt_create_return'] = 'Создать чек возврата';

$lang['sviat__checkbox__order_receipt_date'] = 'Дата формирования чека';
$lang['sviat__checkbox__order_receipt_return'] = 'Возврат';
$lang['sviat__checkbox__order_receipt_print'] = 'Печать';

$lang['sviat__checkbox__no_receipts_title'] = "Чеков ещё нет";
$lang['sviat__checkbox__no_receipts_details'] = "Создайте чек ниже — он появится в этом списке.";
$lang['sviat__checkbox__chain_title'] = "Оплата частями";
$lang['sviat__checkbox__chain_status_partial_paid'] = "Внесён аванс, ждём остаток";
$lang['sviat__checkbox__chain_status_full_paid'] = "Оплачено полностью";
$lang['sviat__checkbox__chain_status_cancelled'] = "Отменено, чеки возвращены";
$lang['sviat__checkbox__chain_status_partial_cancelled'] = "Частично отменено";
$lang['sviat__checkbox__chain_status_unknown'] = "Состояние неизвестно";
$lang['sviat__checkbox__chain_next_partial_paid'] = "Когда поступит остаток — закройте оплату чеком постоплаты.";
$lang['sviat__checkbox__chain_next_full_paid'] = "Делать ничего не нужно: заказ фискализирован полностью.";
$lang['sviat__checkbox__chain_next_cancelled'] = "Средства возвращены. Заказ теперь можно фискализировать обычным чеком продажи.";
$lang['sviat__checkbox__chain_next_unknown'] = "Checkbox не ответил, поэтому действия скрыты — чтобы не выбить лишний чек. Обновите страницу через минуту.";
$lang['sviat__checkbox__np_advance_title'] = "Внесён аванс";
$lang['sviat__checkbox__np_advance_next'] = "К оплате при получении";
$lang['sviat__checkbox__chain_paid'] = "Оплачено";
$lang['sviat__checkbox__chain_of'] = "из";
$lang['sviat__checkbox__chain_left'] = "остаток";
$lang['sviat__checkbox__chain_uah'] = "грн";
$lang['sviat__checkbox__tip_prepayment'] = "Создаст два чека: аванс сейчас, постоплата — когда поступит остаток. Части могут поступить разными способами.";
$lang['sviat__checkbox__tip_after_payment'] = "Остаток может поступить не тем путём, что аванс. Подставлено наиболее вероятное по способу оплаты заказа.";
$lang['sviat__checkbox__advance_amount_placeholder'] = "Сумма аванса, грн";
$lang['sviat__checkbox__errors_source_not_chosen'] = "Выберите, откуда поступили средства — это название печатается в чеке";
$lang['sviat__checkbox__advance_source_label'] = "Откуда поступили средства";
$lang['sviat__checkbox__advance_source_empty'] = "— выберите источник средств —";
$lang['sviat__checkbox__chain_id_settings'] = "Номер заказа в чеке аванса";
$lang['sviat__checkbox__chain_id_help'] = "Checkbox печатает этот номер в чеке аванса строкой «Передплата за замовленням №…». Голый номер заказа не подходит: API требует минимум 10 символов, поэтому его дополняют префиксом или нулями слева.";
$lang['sviat__checkbox__chain_id_limits'] = "custom_relation_id в схеме Checkbox: от 10 до 256 символов, уникальный в пределах организации. Если не передать его вовсе, Checkbox подставит собственный номер вроде 1717502471 — сверить его с заказом будет нечем.";
$lang['sviat__checkbox__chain_id_format'] = "Формат";
$lang['sviat__checkbox__chain_id_format_prefix'] = "Префикс и номер заказа";
$lang['sviat__checkbox__chain_id_format_digits'] = "Только цифры, нули слева";
$lang['sviat__checkbox__chain_id_prefix'] = "Префикс";
$lang['sviat__checkbox__chain_id_preview'] = "В чеке будет";
$lang['sviat__checkbox__sources_settings'] = "Источники средств для аванса";
$lang['sviat__checkbox__sources_remove_name'] = "Убрать платёжную систему";
$lang['sviat__checkbox__sources_add_name'] = "+ Ещё одна платёжная система";
$lang['sviat__checkbox__sources_name_placeholder'] = "Название платёжной системы, напр. NovaPay";
$lang['sviat__checkbox__sources_hide_rare'] = "Свернуть редкие";
$lang['sviat__checkbox__sources_show_rare'] = "Показать редкие средства платежа";
$lang['sviat__checkbox__sources_form_cashless'] = "Безналичная";
$lang['sviat__checkbox__sources_form_cash'] = "Наличная";
$lang['sviat__checkbox__sources_preview'] = "Менеджер увидит";
$lang['sviat__checkbox__sources_help_scenarios'] = "Терминал — «Картка». NovaPay, LiqPay, WayForPay — «Платіж через інтегратора» с названием системы. На IBAN предпринимателя: с карты клиента — «Інтернет банкінг» или «За реквізитами (IBAN)», со счёта клиента — «З поточного рахунку».";
$lang['sviat__checkbox__sources_help_text'] = "Отмеченные источники составляют список, из которого менеджер выбирает, откуда поступил аванс. Выбранная метка печатается в чеке как средство оплаты.";
$lang['sviat__checkbox__sources_help_link'] = "Рекомендации Checkbox по приказу № 601";
$lang['sviat__checkbox__button_prepayment'] = "Выбить чек аванса";
$lang['sviat__checkbox__button_after_payment'] = "Закрыть чеком постоплаты";
$lang['sviat__checkbox__button_refresh_chain'] = "Обновить состояние из Checkbox";
$lang['sviat__checkbox__button_return_advance'] = "Вернуть аванс";
$lang['sviat__checkbox__button_return_all'] = "Вернуть всю оплату";
$lang['sviat__checkbox__test_mode_notice'] = "Касса в тестовом режиме: чеки не являются фискальными документами";
$lang['sviat__checkbox__receipt_tag_test'] = "тест";
$lang['sviat__checkbox__table_type'] = "Тип";
$lang['sviat__checkbox__table_datetime'] = "Дата и время";
$lang['sviat__checkbox__table_id'] = "ID";
$lang['sviat__checkbox__table_actions'] = "Действия";
$lang['sviat__checkbox__alert_no_shift_title'] = "Нет активной смены";
$lang['sviat__checkbox__alert_unfinished_title'] = "Есть незавершённые чеки";
$lang['sviat__checkbox__alert_dont_send_title'] = "Чек не отправляется";
$lang['sviat__checkbox__sale_hidden_by_skip'] = "Чек продажи здесь не предлагается: он выставляется именно по способу оплаты заказа, а тот помечен «не отправлять». Аванс принять можно — вы укажете, откуда на самом деле поступили средства.";
$lang['sviat__checkbox__chain_open_despite_skip'] = "Способ оплаты этого заказа помечен как «не отправлять чек», но аванс уже внесён — закрыть оплату всё равно нужно.";
$lang['sviat__checkbox__advance_hidden_by_paid'] = "Аванс здесь тоже не предлагается: заказ уже отмечен оплаченным, то есть вносить частями нечего. Если средства нужно фискализировать, снимите отметку «Заказ оплачен».";
$lang['sviat__checkbox__receipt_type_sale'] = 'Продажа';
$lang['sviat__checkbox__receipt_type_return'] = 'Возврат';
$lang['sviat__checkbox__receipt_type_prepayment'] = 'Аванс';
$lang['sviat__checkbox__receipt_type_after_payment'] = 'Постоплата';
$lang['sviat__checkbox__orders_receipt_fiscalized'] = 'Фискализирован';

$lang['sviat__left_checkbox_taxes_add'] = 'Добавить налоговую группу';
$lang['sviat__left_checkbox_taxes_code'] = 'Код';
$lang['sviat__left_checkbox_taxes_delete'] = 'Удалить налоговую группу';
$lang['sviat__left_checkbox_taxes_no'] = 'Нет налоговых групп';
$lang['sviat__left_checkbox_taxes_added'] = 'Налоговая группа добавлена';
$lang['sviat__left_checkbox_taxes_updated'] = 'Налоговая группа обновлена';

$lang['sviat__left_checkbox_taxes_errors_code'] = 'Пустой код';
$lang['sviat__left_checkbox_taxes_errors_name'] = 'Пустое название';
$lang['sviat__left_checkbox_taxes_errors_exists'] = 'Налоговая группа с таким кодом уже существует';

$lang['sviat__left_checkbox_taxes_product_tooltip'] = 'Налоговая группа необходима для работы с сервисом Checkbox';

$lang['sviat__checkbox__shifts'] = 'Смены кассира';

$lang['sviat__checkbox__type_cash'] = 'Наличные';
$lang['sviat__checkbox__type_cashless'] = 'Безналичный расчёт';

$lang['sviat__checkbox__message_how_send'] = 'Отправить чек по';
$lang['sviat__checkbox__message_not_send'] = 'Не отправлять';
$lang['sviat__checkbox__message_email'] = 'По Email';

$lang['sviat__checkbox__label_name'] = 'Название для Checkbox';
$lang['sviat__checkbox__type'] = 'Тип оплаты Checkbox';
$lang['sviat__checkbox__dont_send'] = 'Не отправлять в Checkbox';
$lang['sviat__checkbox__payment_method_dont_send'] = 'Для выбранного способа оплаты чеки в Checkbox не создаются';

$lang['sviat__checkbox__installed_at'] = 'Дата начала поиска ТТН для создания чеков';
$lang['sviat__checkbox__installed_at_description'] = 'Чеки будут создаваться только для ТТН, обновлённых после этой даты. Изменяйте только если нужно обработать более старые заказы.';
$lang['sviat__checkbox__installed_at_placeholder'] = 'YYYY-MM-DD HH:MM:SS';

$lang['sviat__checkbox__create_receipt_on_received'] = 'Создавать чек при получении Новой Почтой';
$lang['sviat__checkbox__create_receipt_on_received_no'] = 'Не создавать';
$lang['sviat__checkbox__create_receipt_on_received_yes'] = 'Создавать автоматически';
$lang['sviat__checkbox__create_receipt_on_received_tooltip'] = 'Для автоматического создания чеков при получении заказа Новой Почтой необходимо установить и включить модуль NovaPoshtaTracking';
$lang['sviat__checkbox__create_receipt_on_received_info_title'] = 'Инструкция';
$lang['sviat__checkbox__create_receipt_on_received_info_text'] = 'Для автоматического создания чеков при получении заказа Новой Почтой обязательно нужен модуль NovaPoshtaTracking. Установите и включите модуль: https://github.com/devSviat/NovaPoshtaTracking-OkayCMS';

$lang['sviat__checkbox__cron_setup_title'] = 'Настройка автоматического обновления';
$lang['sviat__checkbox__cron_setup_text_1'] = 'Для того чтобы чеки создавались автоматически при получении заказа Новой Почтой, убедитесь, что на сервере настроено выполнение задач через cron.';
$lang['sviat__checkbox__cron_setup_text_command'] = 'Добавьте в crontab команду';
$lang['sviat__checkbox__cron_setup_text_schedule'] = 'каждую минуту (* * * * *)';
$lang['sviat__checkbox__cron_setup_text_2'] = 'После настройки cron задачи модуль будет автоматически проверять полученные заказы и создавать для них фискальные чеки каждые 10 минут: на 2-й, 12-й, 22-й, 32-й, 42-й и 52-й минуте каждого часа (например: 10:02, 10:12, 10:22, 10:32, 10:42, 10:52).';
$lang['sviat__checkbox__cron_setup_copy_hint'] = 'Нажмите, чтобы скопировать';
$lang['sviat__checkbox__cron_setup_copy_copied'] = '✔ Скопировано в буфер обмена';
$lang['sviat__checkbox__cron_setup_docs_link'] = 'Подробная инструкция по настройке cron задачи на хостинге';

$lang['sviat__checkbox__instructions_title'] = 'Инструкция по настройке';
$lang['sviat__checkbox__instructions_registration_title'] = 'Регистрация в Checkbox:';
$lang['sviat__checkbox__instructions_registration_text'] = 'Для работы с программной кассой Checkbox необходимо зарегистрироваться на портале my.checkbox.ua, настроить кассу и кассира, и получить учётные данные.';
$lang['sviat__checkbox__instructions_registration_link'] = 'Подробная инструкция по регистрации и настройке';
$lang['sviat__checkbox__instructions_credentials_title'] = 'Получение логина, пароля и лицензионного ключа:';
$lang['sviat__checkbox__instructions_credentials_text'] = 'После регистрации на my.checkbox.ua и настройки кассы и кассира вы получите логин и пароль кассира. Лицензионный ключ кассы можно найти в разделе "Кассы" → "Действия" → "Детали" в личном кабинете Checkbox.';
$lang['sviat__checkbox__instructions_receipt_text_title'] = 'Текст для чека:';
