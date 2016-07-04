## Configuração da Aplicação Principal
- Acesse o menu configurações e gere o seu token
- Ainda no menu configurações habilite o processamento local e informe a URL onde a aplicação local está rodando.

## Configuração do Processador Local
- Clone ou faça o download do código fonte do projeto
- Rode composer install
- Renomeie o arquivo local.json.template na pasta config para local.json e coloque o seu token de acesso. O token é gerado na aplicação principal, dentro do menu configurações.
- Se estiver usando nginx, use o arquivo nginx.template que está na pasta config

Pronto, agora basta clicar no botão Local Processor no menu tasks da aplicação principal.

= ) ---