=== CPF Sender API ===
Contributors: labresumos
Tags: woocommerce, edwiser bridge, cpf, api, integration
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plugin WordPress que envia CPF e email de clientes para endpoint externo configurável após compras via WooCommerce/Edwiser Bridge.

== Description ==

O CPF Sender API é um plugin WordPress desenvolvido para o Lab Resumos que automatiza o envio de CPF e email de clientes para uma API externa após a conclusão de compras e matrículas em cursos via Edwiser Bridge.

= Funcionalidades Principais =

* Disparo automático após matrícula no Moodle (via Edwiser Bridge)
* Fallback para WooCommerce puro quando Edwiser Bridge não está ativo
* Envio manual individual por usuário
* Envio em lote (bulk actions) na lista de usuários
* Sistema completo de logs detalhados
* Alertas por email em caso de erro
* Teste de conexão com endpoint
* Delay configurável após matrícula
* Interface administrativa completa

= Como Funciona =

O plugin utiliza o hook `eb_user_courses_updated` do Edwiser Bridge para detectar quando um usuário é matriculado em um curso. Após um delay configurável (padrão: 30 segundos), o plugin busca o CPF do usuário e envia os dados (email e CPF) para o endpoint configurado.

= Meta Keys Suportadas para CPF =

O plugin busca o CPF nas seguintes meta keys (em ordem de prioridade):
* `billing_cpf` (Brazilian Market / Claudio Sanches)
* `billing_document`
* `_wc_billing/address/document` (Fluid Checkout)

= Requisitos =

* WordPress 5.0 ou superior
* WooCommerce (opcional, para fallback)
* Edwiser Bridge (recomendado, para integração completa)

== Installation ==

1. Faça upload do plugin para `/wp-content/plugins/cpf-sender-api/`
2. Ative o plugin através do menu 'Plugins' no WordPress
3. Vá para Configurações > CPF Sender e configure o endpoint da API
4. Configure a API Key e outros parâmetros conforme necessário

== Frequently Asked Questions ==

= O plugin funciona sem Edwiser Bridge? =

Sim, o plugin possui um fallback que utiliza o hook `woocommerce_order_status_completed` quando o Edwiser Bridge não está ativo.

= Qual é o delay padrão após matrícula? =

O delay padrão é de 30 segundos, mas pode ser configurado na página de configurações.

= Onde os logs são armazenados? =

Os logs são armazenados em uma tabela dedicada no banco de dados WordPress (`{prefix}cpf_sender_logs`). Logs antigos (>30 dias) são automaticamente removidos.

= Como funciona o envio manual? =

Na lista de usuários, uma nova coluna "CPF API" é adicionada. Você pode clicar em "Enviar" para cada usuário individualmente ou usar a ação em lote para enviar múltiplos usuários de uma vez.

== Changelog ==

= 1.0.0 =
* Versão inicial
* Disparo automático via Edwiser Bridge
* Fallback WooCommerce
* Envio manual e em lote
* Sistema de logs completo
* Alertas por email
* Teste de conexão
* Interface administrativa completa

== Upgrade Notice ==

= 1.0.0 =
Versão inicial do plugin. Configure o endpoint da API antes de usar.

