# E-mails operacionais

Os templates e o liga/desliga por tipo ficam em `Admin > E-mails`.
As credenciais do provedor continuam no `.env`.

## Desenvolvimento

Por padrao o projeto usa:

```env
MAIL_MAILER=log
```

Assim os e-mails sao escritos no log e nenhum provedor externo e necessario.

## Mailgun

Quando infra for ativar o envio real via Mailgun, ajustar:

```env
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=mg.example.com
MAILGUN_SECRET=key-example
MAILGUN_ENDPOINT=api.mailgun.net
MAILGUN_SCHEME=https
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="${APP_NAME}"
```

Para regioes europeias do Mailgun, usar `MAILGUN_ENDPOINT=api.eu.mailgun.net`.
