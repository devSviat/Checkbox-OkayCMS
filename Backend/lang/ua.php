<?php

$lang['sviat__checkbox__title'] = 'Checkbox ПРРО';
$lang['sviat__checkbox__settings_saved'] = 'Налаштування збережені';
$lang['sviat__checkbox__settings'] = 'Налаштування';
$lang['sviat__checkbox__cashier_credentials'] = 'Облікові дані касира';
$lang['sviat__checkbox__receipt_settings'] = 'Налаштування чеків';
$lang['sviat__checkbox__automation_settings'] = 'Автоматизація';
$lang['sviat__checkbox__login'] = 'Логін';
$lang['sviat__checkbox__login_placeholder'] = 'Введіть логін касира';
$lang['sviat__checkbox__password'] = 'Пароль';
$lang['sviat__checkbox__password_placeholder'] = 'Введіть пароль касира';
$lang['sviat__checkbox__license_key'] = 'Ліцензійний ключ каси';
$lang['sviat__checkbox__license_key_placeholder'] = 'Введіть ліцензійний ключ каси';
$lang['sviat__checkbox__receipt_text_placeholder'] = 'Текст, який буде відображатися в нижній частині чека (можна використовувати {$order_id} для підстановки номера замовлення)';
$lang['sviat__checkbox__order_available_status'] = 'Автоматичне формування та відправка чека при зміні статусу';
$lang['sviat__checkbox__no_status'] = 'Статус не вибраний';
$lang['sviat__checkbox__receipt_text'] = 'Текст для чека';
$lang['sviat__checkbox__receipt_text_order_id_description'] = 'Текст буде відображатися в нижній частині чека. Для підстановки номера замовлення використовуйте:';
$lang['sviat__checkbox__receipt_text_order_id_label'] = 'Номер замовлення';
$lang['sviat__checkbox__create_shift'] = 'Відкрити зміну касира';
$lang['sviat__checkbox__close_shift'] = 'Закрити зміну касира';
$lang['sviat__checkbox__opened_shift'] = 'Зміна відкрита';
$lang['sviat__checkbox__just_created_shift'] = 'Зміна створена, але ще не відкрита';

$lang['sviat__left_checkbox'] = 'Checkbox ПРРО';
$lang['sviat__left_checkbox_settings'] = 'Налаштування';
$lang['sviat__left_checkbox_taxes'] = 'Податкові ставки';
$lang['sviat__left_checkbox_shifts'] = 'Зміни';

$lang['sviat__checkbox__refresh'] = 'Перевірити зміну';
$lang['sviat__checkbox__show_report'] = 'Показати звіт';
$lang['sviat__checkbox__shifts_no'] = 'Немає закритих змін';

$lang['sviat__checkbox__shift_opened_at'] = 'Відкрита';
$lang['sviat__checkbox__shift_closed_at'] = 'Закрита';
$lang['sviat__checkbox__shift_status'] = 'Статус';

$lang['sviat__checkbox__shift_status_created'] = 'Створено';
$lang['sviat__checkbox__shift_status_opening'] = 'Відкривається';
$lang['sviat__checkbox__shift_status_opened'] = 'Відкрито';
$lang['sviat__checkbox__shift_status_closing'] = 'Закривається';
$lang['sviat__checkbox__shift_status_closed'] = 'Закрито';

$lang['sviat__checkbox__errors_empty_params'] = "Не заповнені дані каси. Робота з касою неможлива";
$lang['sviat__checkbox__errors_no_shift'] = "Не створено зміну касира. Робота з касою неможлива";
$lang['sviat__checkbox__errors_find_order'] = "Замовлення не знайдено. Спробуй пізніше";
$lang['sviat__checkbox__errors_find_purchases'] = "Товари в замовлення не знайдено. Спробуй пізніше";
$lang['sviat__checkbox__errors_empty_receipts_isset'] = "Є необроблені чеки. Створити новий чек неможливо";
$lang['sviat__checkbox__errors_receipt_in_progress'] = "Чек вже створюється. Будь ласка, зачекайте";
$lang['sviat__checkbox__errors_unknown_source'] = "Невідоме джерело коштів";
$lang['sviat__checkbox__errors_advance_amount'] = "Сума авансу має бути більшою за нуль і меншою за вартість товарів";
$lang['sviat__checkbox__confirm_return_chain'] = "Повернути всю оплату за замовленням? Буде створено чеки повернення у податковій. Скасувати цю дію окремою кнопкою не можна.";
$lang['sviat__checkbox__errors_chain_is_open'] = "Замовлення оплачується частинами — закрийте його чеком післяплати, а не чеком продажу";
$lang['sviat__checkbox__errors_advance_not_a_number'] = "Вкажіть суму авансу числом, наприклад 500 або 500,50";
$lang['sviat__checkbox__errors_chain_exists'] = "У замовлення вже є внесений аванс";
$lang['sviat__checkbox__errors_sale_exists'] = "Замовлення вже фіскалізовано чеком продажу";
$lang['sviat__checkbox__errors_no_chain'] = "У замовлення немає внесеного авансу";
$lang['sviat__checkbox__errors_chain_closed'] = "Оплату за замовленням уже закрито або повернено";
$lang['sviat__checkbox__errors_after_payment_amount'] = "Сума післяплати має бути більшою за нуль і не перевищувати залишок боргу";

$lang['sviat__checkbox__order_receipts'] = 'Чеки замовлення';
$lang['sviat__checkbox__order_receipt_create'] = 'Фіскалізувати чек';
$lang['sviat__checkbox__order_receipt_create_return'] = 'Створити чек повернення';

$lang['sviat__checkbox__order_receipt_date'] = 'Дата формування чека';
$lang['sviat__checkbox__order_receipt_return'] = 'Повернення';
$lang['sviat__checkbox__order_receipt_print'] = 'Друк';

$lang['sviat__checkbox__no_receipts_title'] = "Чеків ще немає";
$lang['sviat__checkbox__no_receipts_details'] = "Створіть чек нижче — він зʼявиться у цьому списку.";
$lang['sviat__checkbox__chain_title'] = "Оплата частинами";
$lang['sviat__checkbox__chain_status_partial_paid'] = "Внесено аванс, чекаємо решту";
$lang['sviat__checkbox__chain_status_full_paid'] = "Оплачено повністю";
$lang['sviat__checkbox__chain_status_cancelled'] = "Скасовано, чеки повернуто";
$lang['sviat__checkbox__chain_status_partial_cancelled'] = "Частково скасовано";
$lang['sviat__checkbox__chain_status_unknown'] = "Стан невідомий";
$lang['sviat__checkbox__chain_next_partial_paid'] = "Коли надійде решта — закрийте оплату чеком післяплати.";
$lang['sviat__checkbox__chain_next_full_paid'] = "Робити нічого не треба: замовлення фіскалізовано повністю.";
$lang['sviat__checkbox__chain_next_cancelled'] = "Кошти повернено. Замовлення тепер можна фіскалізувати звичайним чеком продажу.";
$lang['sviat__checkbox__chain_next_unknown'] = "Checkbox не відповів, тому дії приховані — щоб не виставити зайвий чек. Оновіть сторінку за хвилину.";
$lang['sviat__checkbox__np_advance_title'] = "Внесено аванс";
$lang['sviat__checkbox__np_advance_next'] = "До сплати при отриманні";
$lang['sviat__checkbox__chain_paid'] = "Сплачено";
$lang['sviat__checkbox__chain_of'] = "з";
$lang['sviat__checkbox__chain_left'] = "залишок";
$lang['sviat__checkbox__chain_uah'] = "грн";
$lang['sviat__checkbox__tip_prepayment'] = "Створить два чеки: аванс зараз, післяплата — коли надійде решта. Частини можуть надійти різними способами.";
$lang['sviat__checkbox__tip_after_payment'] = "Решта може надійти не тим шляхом, що аванс. Підставлено найімовірніше за способом оплати замовлення.";
$lang['sviat__checkbox__advance_amount_placeholder'] = "Сума авансу, грн";
$lang['sviat__checkbox__errors_source_not_chosen'] = "Оберіть, звідки надійшли кошти — ця назва друкується в чеку";
$lang['sviat__checkbox__advance_source_label'] = "Звідки надійшли кошти";
$lang['sviat__checkbox__advance_source_empty'] = "— оберіть джерело коштів —";
$lang['sviat__checkbox__sources_settings'] = "Джерела коштів для авансу";
$lang['sviat__checkbox__sources_remove_name'] = "Прибрати платіжну систему";
$lang['sviat__checkbox__sources_add_name'] = "+ Ще одна платіжна система";
$lang['sviat__checkbox__sources_name_placeholder'] = "Назва платіжної системи, напр. NovaPay";
$lang['sviat__checkbox__sources_hide_rare'] = "Згорнути рідковживані";
$lang['sviat__checkbox__sources_show_rare'] = "Показати рідковживані засоби платежу";
$lang['sviat__checkbox__sources_form_cashless'] = "Безготівкова";
$lang['sviat__checkbox__sources_form_cash'] = "Готівка";
$lang['sviat__checkbox__sources_preview'] = "Менеджер побачить";
$lang['sviat__checkbox__sources_help_scenarios'] = "Термінал — «Картка». NovaPay, LiqPay, WayForPay — «Платіж через інтегратора» з назвою системи. На IBAN підприємця: з картки клієнта — «Інтернет банкінг» або «За реквізитами (IBAN)», з рахунку клієнта — «З поточного рахунку».";
$lang['sviat__checkbox__sources_help_text'] = "Позначені джерела складають список, з якого менеджер обирає, звідки надійшов аванс. Обрана мітка друкується в чеку як засіб оплати.";
$lang['sviat__checkbox__sources_help_link'] = "Рекомендації Checkbox за наказом № 601";
$lang['sviat__checkbox__button_prepayment'] = "Виставити чек авансу";
$lang['sviat__checkbox__button_after_payment'] = "Закрити чеком післяплати";
$lang['sviat__checkbox__button_refresh_chain'] = "Оновити стан із Checkbox";
$lang['sviat__checkbox__button_return_advance'] = "Повернути аванс";
$lang['sviat__checkbox__button_return_all'] = "Повернути всю оплату";
$lang['sviat__checkbox__test_mode_notice'] = "Каса в тестовому режимі: чеки не є фіскальними документами";
$lang['sviat__checkbox__receipt_tag_test'] = "тест";
$lang['sviat__checkbox__table_type'] = "Тип";
$lang['sviat__checkbox__table_datetime'] = "Дата й час";
$lang['sviat__checkbox__table_id'] = "ID";
$lang['sviat__checkbox__table_actions'] = "Дії";
$lang['sviat__checkbox__alert_no_shift_title'] = "Немає активної зміни";
$lang['sviat__checkbox__alert_unfinished_title'] = "Є незавершені чеки";
$lang['sviat__checkbox__alert_dont_send_title'] = "Чек не відправляється";
$lang['sviat__checkbox__sale_hidden_by_skip'] = "Чек продажу тут не пропонується: він виставляється саме за способом оплати замовлення, а той позначено «не відправляти». Аванс можна прийняти — ви вкажете, звідки насправді надійшли кошти.";
$lang['sviat__checkbox__chain_open_despite_skip'] = "Спосіб оплати цього замовлення позначено як «не відправляти чек», але аванс уже внесено — закрити оплату все одно потрібно.";
$lang['sviat__checkbox__receipt_type_sale'] = 'Продаж';
$lang['sviat__checkbox__receipt_type_return'] = 'Повернення';
$lang['sviat__checkbox__receipt_type_prepayment'] = 'Аванс';
$lang['sviat__checkbox__receipt_type_after_payment'] = 'Післяплата';
$lang['sviat__checkbox__orders_receipt_fiscalized'] = 'Фіскалізовано';


$lang['sviat__left_checkbox_taxes_add'] = 'Додати податкову групу';
$lang['sviat__left_checkbox_taxes_code'] = 'Код';
$lang['sviat__left_checkbox_taxes_delete'] = 'Видалити податкову групу';
$lang['sviat__left_checkbox_taxes_no'] = 'Немає податкових груп';
$lang['sviat__left_checkbox_taxes_added'] = 'Податкова група додана';
$lang['sviat__left_checkbox_taxes_updated'] = 'Податкову групу оновлено';

$lang['sviat__left_checkbox_taxes_errors_code'] = 'Порожній код';
$lang['sviat__left_checkbox_taxes_errors_name'] = 'Порожня назва';
$lang['sviat__left_checkbox_taxes_errors_exists'] = 'Податкова група з таким кодом існує';

$lang['sviat__left_checkbox_taxes_product_tooltip'] = 'Податкова група потрібна для роботи з сервісом Checkbox';

$lang['sviat__checkbox__shifts'] = 'Зміни касира';

$lang['sviat__checkbox__type_cash'] = 'Готівка';
$lang['sviat__checkbox__type_cashless'] = 'Безготівковий розрахунок';

$lang['sviat__checkbox__message_how_send'] = 'Надіслати чек по';
$lang['sviat__checkbox__message_not_send'] = 'Не відправляти';
$lang['sviat__checkbox__message_email'] = 'По Email';

$lang['sviat__checkbox__label_name'] = 'Назва для Checkbox';
$lang['sviat__checkbox__type'] = 'Тип оплати Checkbox';
$lang['sviat__checkbox__dont_send'] = 'Не відправляти до Checkbox';
$lang['sviat__checkbox__payment_method_dont_send'] = 'Для обраного типу оплати чеки в Checkbox не створюються';

$lang['sviat__checkbox__installed_at'] = 'Дата початку пошуку ТТН для створення чеків';
$lang['sviat__checkbox__installed_at_description'] = 'Чеки створюватимуться лише для ТТН, оновлених після цієї дати. Змінюйте лише якщо потрібно обробити старіші замовлення.';
$lang['sviat__checkbox__installed_at_placeholder'] = 'YYYY-MM-DD HH:MM:SS';

$lang['sviat__checkbox__create_receipt_on_received'] = 'Створювати чек при отриманні Новою Поштою';
$lang['sviat__checkbox__create_receipt_on_received_no'] = 'Не створювати';
$lang['sviat__checkbox__create_receipt_on_received_yes'] = 'Створювати автоматично';
$lang['sviat__checkbox__create_receipt_on_received_tooltip'] = 'Для автоматичного створення чеків при отриманні замовлення Новою Поштою необхідно встановити та увімкнути модуль NovaPoshtaTracking';
$lang['sviat__checkbox__create_receipt_on_received_info_title'] = 'Інструкція';
$lang['sviat__checkbox__create_receipt_on_received_info_text'] = 'Для автоматичного створення чеків при отриманні замовлення Новою Поштою обов\'язково потрібен модуль NovaPoshtaTracking. Встановіть та увімкніть модуль: https://github.com/devSviat/NovaPoshtaTracking-OkayCMS';

$lang['sviat__checkbox__cron_setup_title'] = 'Налаштування автоматичного оновлення';
$lang['sviat__checkbox__cron_setup_text_1'] = 'Для того, щоб чеки створювалися автоматично при отриманні замовлення Новою Поштою, вам слід переконатися, що на сервері налаштовано виконання за допомогою cron системного планувальника завдань.';
$lang['sviat__checkbox__cron_setup_text_command'] = 'Додайте в crontab команду';
$lang['sviat__checkbox__cron_setup_text_schedule'] = 'щохвилини (* * * * *)';
$lang['sviat__checkbox__cron_setup_text_2'] = 'Після налаштування cron завдання, модуль автоматично перевірятиме отримані замовлення та створюватиме для них фіскальні чеки кожні 10 хвилин: на 2-й, 12-й, 22-й, 32-й, 42-й та 52-й хвилині кожної години (наприклад: 10:02, 10:12, 10:22, 10:32, 10:42, 10:52).';
$lang['sviat__checkbox__cron_setup_copy_hint'] = 'Натисніть, щоб скопіювати';
$lang['sviat__checkbox__cron_setup_copy_copied'] = '✔ Скопійовано в буфер обміну';
$lang['sviat__checkbox__cron_setup_docs_link'] = 'Детальна інструкція з налаштування cron завдання на хостингу';

$lang['sviat__checkbox__instructions_title'] = 'Інструкція з налаштування';
$lang['sviat__checkbox__instructions_registration_title'] = 'Реєстрація в Checkbox:';
$lang['sviat__checkbox__instructions_registration_text'] = 'Для роботи з програмною касою Checkbox необхідно зареєструватися на порталі my.checkbox.ua та налаштувати касу, касира та отримати облікові дані.';
$lang['sviat__checkbox__instructions_registration_link'] = 'Детальна інструкція з реєстрації та налаштування';
$lang['sviat__checkbox__instructions_credentials_title'] = 'Отримання логіну, паролю та ліцензійного ключа:';
$lang['sviat__checkbox__instructions_credentials_text'] = 'Після реєстрації на my.checkbox.ua та налаштування каси та касира, ви отримаєте логін та пароль касира. Ліцензійний ключ каси можна знайти в розділі "Каси" → "Дії" → "Деталі" у вашому особистому кабінеті Checkbox.';
$lang['sviat__checkbox__instructions_receipt_text_title'] = 'Текст для чека:';
