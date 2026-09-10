# Reverb em produção

Para `https://efomento.secult.local`, com Docker Compose atrás de um proxy HTTPS:

- Navegador → `wss://efomento.secult.local:443/app/{key}` → proxy HTTPS → Nginx do Compose → `reverb:8080`.
- Laravel/queue → `http://reverb:8080` pela rede Docker.

## Ambiente

Atualize estas variáveis no `.env` do servidor, preservando as demais configurações:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://efomento.secult.local
BROADCAST_CONNECTION=reverb

REVERB_HOST=reverb
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_ALLOWED_ORIGINS=efomento.secult.local
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=efomento.secult.local
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

Configure `REVERB_APP_ID`, `REVERB_APP_KEY` e `REVERB_APP_SECRET` com os valores
de produção, iguais em todos os serviços. Para uma instalação nova, gere valores
aleatórios (por exemplo, `openssl rand -hex 32`). Somente a chave pública
`REVERB_APP_KEY` deve ser enviada ao frontend; o segredo permanece no servidor.

As variáveis `VITE_REVERB_*` são incorporadas ao JavaScript durante o build.
Defina host, porta e esquema explicitamente: o destino público é diferente do
destino interno. Use `.env` também para os processos PHP; `.env.production`
sozinho não configura todos os serviços do Compose.

## Proxy HTTPS externo

No bloco HTTPS existente de `efomento.secult.local`, encaminhe `/app/` ao Nginx
do Compose, preservando o caminho e os cabeçalhos de upgrade. Exemplo para um
proxy Nginx executado no mesmo host Docker:

```nginx
location ^~ /app/ {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Host $http_host;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto https;
    proxy_read_timeout 600s;
    proxy_send_timeout 600s;
    proxy_buffering off;
}
```

Se o proxy estiver em outro servidor/container, substitua `127.0.0.1:8080` pelo
endereço do Nginx do Compose acessível ao proxy. Mantenha o certificado TLS válido
para o domínio e confiável pelos navegadores. O Nginx interno já encaminha
`/app/` ao Reverb. As publicações em `/apps/` usam a rede Docker diretamente.

## Aplicar

Após atualizar os arquivos e o `.env` no servidor:

```sh
docker compose exec -T app npm run build
docker compose exec -T app php artisan config:cache
docker compose up -d --no-deps --force-recreate queue reverb
docker compose exec -T nginx nginx -t
docker compose exec -T nginx nginx -s reload
```

Valide e recarregue também o proxy HTTPS externo após editar sua configuração.
Em produção, use os assets compilados: pare o serviço `vite` caso esteja ativo
e remova `public/hot` caso exista. Os processos Reverb e queue precisam de uma
política de reinício/supervisão no ambiente de produção. A porta 8081 publicada
pelo Compose é usada no desenvolvimento; restrinja seu acesso em produção,
onde o navegador usa apenas a porta 443.

## Verificar

Abra a aplicação autenticada e confira no DevTools → Network → WS uma conexão
com `wss://efomento.secult.local/app/...` e status `101 Switching Protocols`.
Dispare uma notificação e confirme seu recebimento sem recarregar a página.
Para diagnóstico: `docker compose logs --tail=100 reverb queue nginx`.

Referência: [Laravel Reverb — produção](https://laravel.com/docs/13.x/reverb#running-reverb-in-production).
