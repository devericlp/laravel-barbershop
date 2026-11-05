# BarberShop

Plataforma responsiva de agendamento para barbearias. É um projeto demonstrativo de portfólio, com várias barbearias isoladas entre si (multi-tenant) em um único banco de dados.

## Sobre o projeto

### Funcionalidades

- Gerenciar barbearias
- Gerenciar perfis e permissões
- Gerenciar serviços
- Gerenciar profissionais
- Gerenciar clientes
- Gerenciar gerentes
- Definir expedientes dos profissionais
- Agendar serviços em horários livres
- Acompanhar a agenda e os atendimentos
- Concluir atendimentos ou marcar não comparecimento
- Cancelar agendamentos
- Consultar indicadores no dashboard

### Perfis

- **Admin** da plataforma: cria e gerencia as barbearias e controla perfis e permissões.
- **Gerente**: cadastra serviços, profissionais, clientes e expedientes, acompanha os agendamentos e consulta indicadores.
- **Profissional**: vê a própria agenda e conclui ou marca não comparecimento nos atendimentos.
- **Cliente**: escolhe a barbearia, agenda um horário livre, vê as próprias reservas e cancela até 2 horas antes.

A interface está em português do Brasil e inglês, com tema claro, escuro ou do sistema. Valores em reais (R$) e horários no fuso `America/Sao_Paulo`.

## Tecnologias

- PHP 8.3+ e [Laravel 13](https://laravel.com)
- [Livewire 4](https://livewire.laravel.com) e [Alpine.js](https://alpinejs.dev)
- [Flux UI](https://fluxui.dev) (Flux Pro)
- [Tailwind CSS 4](https://tailwindcss.com) e Vite
- [spatie/laravel-permission](https://spatie.be/docs/laravel-permission) para perfis e permissões
- SQLite (padrão)
- Pest, Larastan e Pint para testes, análise estática e estilo de código

## Como rodar

Requisitos: PHP 8.3+, Composer, Node.js e uma licença do Flux Pro (credenciais em `auth.json`).

```bash
composer setup
php artisan migrate:fresh --seed
composer dev
```

## Verificação

```bash
composer test   # Pint, Larastan e testes
```
