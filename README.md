##Микросервис баланса пользователей

Технологический стек: nginx, php8, mysql, rabbitMQ

dump.sql - база данных MYSQL\
config.ini - файл с настройками приложения

publisher.php - кладет сообщения в очередь\
receiver.php - читает сообщения из очереди 

Сервис работает с 3-мя типами сообщений в формате json:

####Enrollment - Зачисление
Список полей:\
type - "enrollment"\
data - массив\
-- user_id - int\
-- balance - float\
-- timestamp - string (Y-m-d)

Пример:\
{"type":"enrollment","data":{"user_id":"1","balance":"150.50","timestamp":"2021-09-10 07:00:00"}}

####WriteOff - Списание
Список полей:\
type - "writeOff"\
data - массив\
-- user_id - int\
-- balance - float\
-- timestamp - string (Y-m-d)

Пример:\
{"type":"writeOff","data":{"user_id":"1","balance":"150.50","timestamp":"2021-09-10 07:00:00"}}

####Transfer - Перевод между пользователями
Список полей:\
type - "transfer"\
data - массив\
-- from_user_id - int\
-- to_user_id - int\
-- balance - float\
-- timestamp - string (Y-m-d)

Пример:\
{"type":"transfer","data":{"from_user_id":"1","to_user_id":"2","balance":"150.50","timestamp":"2021-09-10 07:00:00"}}