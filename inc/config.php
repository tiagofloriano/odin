<?php
/**
 * Configuração da conexão com o banco de dados do Odin.
 *
 * Defina as credenciais conforme o ambiente do servidor.
 */

// Cria a conexão PDO usando a função da Bigboss.
$pdo = con('usuario', 'senha');

// Seleciona o banco utilizado pelo Odin.
db('nomedobanco');