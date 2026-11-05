Для выполнения задания использовал агента cline в phpstorm. Модель Qwen 3.8 Max 0902
Вот 3 основных первых промпта, которые собрали большую часть задания
Ты — senior Laravel-разработчик. Проект на Laravel 13, PHP 8.2+, PostgreSQL 17. В проекте установлен Laravel Boost.

Мне нужно переписать метод BookingController@store.

1. Валидация и DTO (без spatie/laravel-data) Создай DTO App\DTOs\StoreBookingData. Используй нативные PHP-атрибуты (#[Attribute]) для валидации, так как Laravel 13 их поддерживает.

Поля: room_id (int), starts_at (string ISO 8601), ends_at (string ISO 8601).
Правила: room_id существует в таблице rooms. Даты — валидный формат. Разница между датами: минимум 15 минут, максимум 4 часа, шаг 15 минут.
В контроллере инъектируй DTO в сигнатуру метода. Валидация должна срабатывать до входа в сервис.

2. Сервис-слой и таймзоны Вынеси логику в App\Services\BookingService.

Парсинг дат: используй Carbon::parse($dto->starts_at, $room->office->timezone).
Проверка рабочего времени: убедись, что начало брони попадает в интервал 09:00–20:00 в таймзоне офиса. Если нет — бросай кастомный exception App\Exceptions\OutsideWorkingHoursException.

3. Проверка наложений и ограничения БД (PostgreSQL 17)

Уровень БД: напиши миграцию, которая удаляет старый уникальный индекс (если есть) и добавляет EXCLUDE constraint для таблицы bookings: ALTER TABLE bookings ADD CONSTRAINT bookings_no_overlap EXCLUDE USING gist (room_id WITH =, tsrange(starts_at, ends_at) WITH &&); (Убедись, что включено расширение btree_gist).
Уровень приложения: в сервисе перед вставкой сделай легковесный SELECT ... FOR UPDATE или используй on conflict do nothing.
Логика: если конфликт найден, сервис должен бросать кастомный exception App\Exceptions\BookingConflictException.
Атомарность: оберни проверку и создание в DB::transaction.

4. Логирование Используй Boost-контекст: Log::withContext(['booking_id' => $id, 'room_id' => $room_id]). Канал bookings.

5. Результат

Контроллер должен быть тонким: получить DTO -> вызвать сервис -> вернуть JSON с ID или ошибку.
Сервис возвращает ?int (ID созданной записи или null, если сработал ON CONFLICT DO NOTHING).
Сгенерируй код DTO, Сервиса, кастомных Exception, миграции и обновленный контроллер.
Сгенерируй Pest-тесты для всех сценариев: успешное создание, наложение времени, нерабочее время, выход за лимиты длительности.

Используй контекст Boost для поиска правил валидации дат и работы с tsrange в PostgreSQL

Обнови метод сервиса createBooking.

Убери lockForUpdate и ensureNoConflict.
Оставь ensureWorkingHours (с учетом таймзоны Room->Office).
Внутри DB::transaction создай новую модель Booking и вызови $booking->save().
Оберни $booking->save() в try-catch. Лови Illuminate\Database\QueryException.
Внутри catch проверяй код ошибки или текст сообщения на предмет конфликта EXCLUDE constraint (SQLSTATE 23505). Если это конфликт — бросай кастомный App\Exceptions\BookingConflictException.
Если сохранение прошло успешно — пиши лог и возвращай модель Booking.
Сгенерируй код кастомного исключения BookingConflictException (должен наследоваться от \Exception или \LogicException).

Добавь валидацию входящих данных времени. Максимальный интервал бронирования не должен превышать 4 часа, минимальный 15 минут, забронированный интервал кратен 15 минутам. 15, 30, 45 и так далее



Ты — senior Laravel-разработчик. Проект на Laravel 13.

Мне нужно внедрить внедрение зависимостей (DI) для логгера и изменить область жизни (scope) контроллера и сервиса на синглтон.

1. Контракт логгера

BookingController и BookingService должны принимать логгер через конструктор, используя тип-хинтинг интерфейса Psr\Log\LoggerInterface.
Не используй фасад Log::.

2. Канал логирования

Внутри сервис-контейнера нужно создать именованный биндинг или фабрику для LoggerInterface, которая возвращает инстанс логгера с каналом 'bookings'.
Код: Log::channel('bookings')->getLogger().

3. Область жизни (Scope)

4. Реализация (Service Provider)

Создай (или отредактируй) App\Providers\AppServiceProvider.
В методе register() выполни привязки.
Для логгера: $this->app->instance(LoggerInterface::class, Log::channel('bookings')->getLogger()); или через bind с замыканием.
Для контроллера и сервиса: $this->app->singleton(BookingController::class, ...) и $this->app->singleton(BookingService::class, ...). В замыкании синглтона сервиса инъектируй логгер.

5. Обновление кода

Сгенерируй код AppServiceProvider.
Обнови сигнатуры конструкторов BookingController и BookingService.
Удали все упоминания фасада use Illuminate\Support\Facades\Log; из контроллера и сервиса, а также все вызовы Log::channel(...).
Используй $this->logger->info('Booking created', [...])

Структурированные ИИ промпты делал через ИИ чат, где в свободной форме описывал поведение системы. Так поступил для экономии токенов в запросах агента. Изначально задание было на insert с проверкой ограничения. ИИ сделал это через отдельный запрос с блокировкой комнаты. На ревью заметил это. Данное решение только затормозит процесс записи в комнату, даже если тайминги не пересекаются. Так как Eloquent не позволяет собрать запрос с таким insert, ИИ выбрал свой вариант. Я решил изменить с кастомного sql запроса на проверку конкретного исключения от бд.  
По промптам видно, что несколько раз просил ИИ добавить валидацию шага временного интервала и учета таймзоны.