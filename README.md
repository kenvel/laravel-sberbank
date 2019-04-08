# Simple Sberbank acquiring library.
Простая библиотека для приема платежей через интернет для Сбербанк.

### Возможности

 * Генерация URL для оплаты товаров
 * Просмотр статуса платжа

### Установка

С помощью [composer](https://getcomposer.org/):

```bash
composer require kenvel/laravel-sberbank
```

Подключение в контроллере:

```php
use Kenvel\Sberbank;
```

## Примеры использования
### 1. Инициализация

```php
$acquiring_url = 'https://securepayments.sberbank.ru';
$access_token  = 'sberbank_secret_token';

$sberbank = new Sberbank($acquiring_url, $access_token);
```

### 2. Получить URL для оплаты
```php
//Подготовка массива с данными об оплате
$payment = [
    'orderNumber'   => '1234567',
    'amount'        => 100,
    'language'      => 'ru',
    'description'   => 'New payment',
    'returnUrl'     => 'http://my.site/successful-payment',
    'failUrl'       => 'http://my.site/fail-payment',
];

//Получение url для оплаты
$result = $sberbank->paymentURL($payment);

//Контроль ошибок
if(!$result['success']){
  echo($result['error']);
} else{
  $payment_id = $result['payment_id'];
  header('Location: ' . $result['payment_url']);
}
```

### 3. Получить статус платежа
```php
//$payment_id Идентификатор платежа банка (полученый в пункте "2 Получить URL для оплаты")

$result = $sberbank->getState($payment_id)

//Контроль ошибок
if(!$result['success']){
  echo($result['error']);
} else{
  echo($result['payment_status']);
}
```

---

[![Donate button](https://www.paypal.com/en_US/i/btn/btn_donate_SM.gif)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=FGCHZNKKVG622&source=url)

*Если вы нашли этот проект полезным, пожалуйста сделайте небольшой донат - это поможет мне улучшить код*
