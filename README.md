<h1>Plugin Vindi para Gravity Forms (WordPress)</h1>

Este plugin permite integrar formulários do Gravity Forms com a plataforma de pagamentos Vindi, utilizando conexão via API REST.

<b>⚙️ Configuração</b>

Na página de configurações do plugin, é necessário informar:
	•	URL da API da Vindi
	•	Token JWT da conta do cliente

Essas credenciais serão utilizadas para autenticar as requisições ao ambiente da Vindi.

<b>📤 Envio de dados ao Checkout</b>

O plugin captura os seguintes campos do formulário e os envia automaticamente ao checkout da Vindi:
	•	Nome
	•	Sobrenome
	•	Email
	•	CPF

🔧 Os IDs dos campos devem ser informados diretamente no código do plugin, conforme a estrutura do formulário.

<b>🔄 Campos adicionais</b>

Os parâmetros telefone e endereço não estão ativos por padrão, mas o código do plugin pode ser facilmente adaptado para incluí-los, caso necessário.

<b>📦 Instalação</b>
	1.	Faça o upload da pasta do plugin no diretório /wp-content/plugins/.
	2.	Ative o plugin no painel do WordPress.
	3.	Acesse o menu de configurações do plugin e insira suas credenciais da Vindi.
	4.	Configure o formulário do Gravity Forms com os campos necessários.

<b>🧪 Testes</b>

Recomenda-se utilizar o ambiente de sandbox da Vindi para testes antes de ir para produção.
