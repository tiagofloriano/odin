# Guia de contribuição — Odin

Obrigado pelo interesse em contribuir com o Odin!

Este documento apresenta as orientações para propor melhorias, corrigir problemas e desenvolver novas funcionalidades para o projeto.

## 1. Objetivo do projeto

O Odin é um sistema de código aberto voltado à autenticação, ao controle de acesso e ao gerenciamento de aplicações web, com recursos para criação de módulos, edição de código e construção de interfaces para manipulação de dados.

Optamos por não utilizar nenhum framework.

As contribuições devem priorizar a segurança, a simplicidade, a facilidade de manutenção e a compatibilidade com a arquitetura do sistema.

## 2. Antes de começar

Antes de desenvolver uma funcionalidade ou correção:

1. Consulte as issues existentes para verificar se o problema ou a proposta já foi registrado.
2. Se a mudança for significativa, abra uma issue para discutir a ideia antes de começar a implementação.
3. Verifique se a proposta está alinhada aos objetivos do Odin.

Correções pequenas e bem delimitadas podem ser propostas diretamente por meio de um Pull Request.

## 3. Branches do projeto

O desenvolvimento utiliza as seguintes branches principais:

- `master`: versão estável do projeto (vazio pois a versão beta seguiu em constante desenvolvimento).
- `beta`: versão antiga, em vigor atualmente.
- `php8`: branch de desenvolvimento da nova versão.

Novas funcionalidades e correções devem ser desenvolvidas em branches próprias, criadas a partir da `php8`.

Exemplos de nomes:

- `feature/formbuilder`
- `feature/editor-codigo`
- `fix/login`
- `fix/validacao-dados`
- `docs/readme`

Não envie alterações diretamente para `master`.

## 4. Processo de contribuição

Para contribuir:

1. Faça um fork do repositório ou utilize uma branch própria, conforme as permissões disponíveis.
2. Atualize sua cópia a partir da branch `php8`.
3. Crie uma branch para a funcionalidade ou correção.
4. Implemente as alterações.
5. Revise o código e teste o comportamento afetado.
6. Faça commits com mensagens claras e objetivas.
7. Envie a branch ao GitHub.
8. Abra um Pull Request direcionado à branch `php8`.

Descreva no Pull Request o que foi alterado, por que a mudança é necessária e como ela foi testada.

## 5. Padrões de desenvolvimento

As contribuições devem seguir estas orientações:

- Utilizar PHP 8 e evitar recursos incompatíveis com a versão mínima adotada pelo projeto.
- Priorizar código legível, organizado e fácil de manter.
- Reutilizar as funções e classes existentes quando apropriado.
- Evitar duplicação desnecessária de código.
- Validar os dados recebidos e utilizar consultas parametrizadas para interações com o banco de dados.
- Não incluir senhas, tokens, credenciais ou outros dados sensíveis no código.
- Evitar alterações não relacionadas à finalidade do Pull Request.
- Documentar funcionalidades novas ou comportamentos que não sejam evidentes.

Quando houver dúvidas sobre a arquitetura ou os padrões adotados, abra uma issue antes de introduzir mudanças estruturais.

## 6. Segurança

A segurança é uma prioridade no Odin.

Contribuições que envolvam autenticação, sessões, permissões, acesso a arquivos, uploads, consultas ao banco de dados ou execução de código devem ser revisadas com atenção especial.

Não publique credenciais, dados pessoais ou informações sensíveis em issues ou Pull Requests.

Vulnerabilidades de segurança não devem ser divulgadas publicamente antes de uma avaliação. Entre em contato diretamente com o mantenedor para combinar uma forma segura de comunicação.

## 7. Pull Requests e revisão

Todo Pull Request está sujeito à análise do mantenedor.

Durante a revisão, poderão ser solicitados esclarecimentos, ajustes ou testes adicionais.

Um Pull Request poderá ser aceito, recusado ou mantido em discussão. A abertura de uma solicitação não garante sua integração ao projeto.

As alterações devem ser direcionadas à branch `php8`. A integração à branch `master` será realizada pelo mantenedor após a avaliação e a validação da versão.

## 8. Estrutura do projeto

O Odin organiza seus arquivos por responsabilidade, separando bibliotecas, funcionalidades gerais, módulos, páginas e elementos visuais.

```text
/
├── inc/
│   ├── index.php
│   ├── bigboss.php
│   └── odin.php
├── scripts/
│   ├── index.php
│   └── notifmail.php
├── uploads/
│   └── index.php
├── themes/
│   ├── index.php
│   └── padrao/
│       ├── index.php
│       ├── header.php
│       ├── footer.php
│       ├── loginheader.php
│       ├── loginfooter.php
│       ├── bootstrap/
│       └── jquery/
├── mod/
│   ├── index.php
│   └── modulopadrao/
│       ├── index.php
│       └── modulo.php
├── pages/
│   ├── index.php
│   ├── home.php
│   ├── sair.php
│   ├── login.php
│   └── 404.php
└── index.php
```

### Diretórios e arquivos

- **`inc/`** — arquivos essenciais do sistema.
  - `index.html`: arquivo de proteção do diretório.
  - `bigboss.php`: biblioteca de funções auxiliares para agilizar o desenvolvimento.
  - `odin.php`: biblioteca de classes específicas do Odin.

- **`scripts/`** — funcionalidades de uso geral.
  - `index.html`: arquivo de proteção do diretório.
  - `notifmail.php`: funções para envio de e-mails de notificação.

- **`uploads/`** — diretório destinado aos arquivos enviados por meio da aplicação.
  - `index.html`: arquivo de proteção do diretório.

- **`themes/`** — temas e elementos visuais do sistema.
  - `index.html`: arquivo de proteção do diretório.
  - **`padrao/`** — tema padrão do Odin.
    - `index.html`: arquivo de proteção do diretório.
    - `header.php` e `footer.php`: cabeçalho e rodapé das páginas após a autenticação.
    - `loginheader.php` e `loginfooter.php`: estruturas independentes de cabeçalho e rodapé para a página de login.
    - `bootstrap/`: arquivos CSS do tema.
    - `jquery/`: arquivos JavaScript do tema.

- **`mod/`** — módulos com funcionalidades específicas para um ponto de acesso ou para uso multiusuário, como financeiro e relatórios.
  - `index.html`: arquivo de proteção do diretório.
  - **`modulopadrao/`** — exemplo de módulo para o desenvolvimento de novas funcionalidades.
    - `index.html`: arquivo de proteção do diretório.
    - `modulo.php`: classes-base e classes específicas do módulo.

- **`pages/`** — páginas padrão do sistema.
  - `index.html`: arquivo de proteção do diretório.
  - `home.php`: página inicial padrão, utilizada quando não há outra configurada para o ponto de acesso ou usuário.
  - `sair.php`: encerra a sessão do usuário.
  - `login.php`: formulário de autenticação.
  - `404.php`: página exibida quando o arquivo solicitado não é encontrado.

- **`index.php`** — ponto de entrada principal da aplicação.

## 9. Licença e autoria

Ao enviar uma contribuição, o autor declara que tem o direito de disponibilizar o código proposto e que não está violando direitos de terceiros.

As contribuições aceitas serão distribuídas sob a licença adotada pelo Odin, observadas as condições aplicáveis e os direitos dos respectivos autores.

Não envie código copiado de projetos com licenças incompatíveis com a licença do Odin.

## 10. Conduta

Espera-se que todos os participantes mantenham uma comunicação respeitosa, construtiva e inclusiva.

Críticas técnicas são bem-vindas, desde que contribuam para a melhoria do projeto e sejam apresentadas de maneira respeitosa.

## 11. Dúvidas

Caso tenha dúvidas sobre o funcionamento do projeto, a arquitetura ou o processo de contribuição, abra uma issue no repositório ou entre em contato com o mantenedor (tiago@abfisica.org.br).