# Vote Church API — Documentação

## Visão Geral

API REST em Laravel. Todas as respostas são JSON no formato:

```json
{ "success": true, "data": { ... } }
{ "success": false, "message": "..." }
```

Base URL: `/api`

---

## Autenticação

A API usa **JWT**. A maioria das rotas exige o header:

```
Authorization: Bearer {token}
```

### Rotas de Auth

| Método | Rota | Auth | Descrição |
|--------|------|------|-----------|
| POST | `/auth/login` | Não | Login |
| POST | `/auth/register` | Não | Registro |
| POST | `/auth/logout` | Sim | Logout |
| POST | `/auth/refresh` | Sim | Renovar token |
| GET | `/auth/me` | Sim | Usuário logado |

**Login — body:**
```json
{ "email": "admin@email.com", "password": "senha" }
```

**Login — resposta:**
```json
{ "success": true, "token": "eyJ...", "user": { "id": 1, "name": "...", "permission": "admin" } }
```

Permissões: `admin` ou `viewer`.

---

## Membros

Membros são as pessoas da congregação (candidatos em eleições, escalados em cultos).

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/members` | Listar membros |
| POST | `/members` | Criar membro |
| GET | `/members/{id}` | Ver membro |
| PUT | `/members/{id}` | Atualizar membro |
| DELETE | `/members/{id}` | Remover (soft delete) |
| POST | `/members/validate-cpf` | Validar CPF (rota pública) |

**Campos:**
```json
{
  "name": "João Silva",
  "cpf": "000.000.000-00",
  "rg": "0000000",
  "description": "...",
  "member_since": "2020-01-15",
  "photo": "url_ou_base64",
  "status": "active"
}
```

`status`: `active` ou `inactive`.

---

## Ministérios

Ministérios agrupam membros por área de atuação (Louvor, Mídia, Recepção, etc.).

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/ministries` | Listar ministérios |
| POST | `/ministries` | Criar ministério |
| GET | `/ministries/{id}` | Ver ministério (inclui usuários vinculados) |
| PUT | `/ministries/{id}` | Atualizar |
| DELETE | `/ministries/{id}` | Remover |
| POST | `/ministries/{id}/members` | Adicionar membro ao ministério |
| DELETE | `/ministries/{id}/members/{memberId}` | Remover membro do ministério |
| POST | `/ministries/{id}/users` | Adicionar usuário do sistema ao ministério |
| DELETE | `/ministries/{id}/users/{userId}` | Remover usuário do ministério |

**Criar ministério — body:**
```json
{ "name": "Louvor" }
```

**Adicionar membro — body:**
```json
{ "member_id": 5 }
```

---

## Programação (Schedules)

Um schedule define um **tipo de evento recorrente ou avulso** da igreja.

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/schedules` | Listar schedules (aceita `?type=recurring\|single`) |
| POST | `/schedules` | Criar schedule |
| GET | `/schedules/{id}` | Ver schedule |
| PUT | `/schedules/{id}` | Atualizar |
| DELETE | `/schedules/{id}` | Remover |

**Campos:**
```json
{
  "name": "Culto Dominical",
  "type": "recurring",
  "day_of_week": 0,
  "time": "19:00",
  "description": "Culto principal da semana",
  "ministries": [1, 2, 3]
}
```

- `type`: `recurring` (semanal) ou `single` (avulso)
- `day_of_week`: obrigatório se `recurring`. `0` = Domingo, `1` = Segunda … `6` = Sábado
- `date`: obrigatório se `single`. Formato `YYYY-MM-DD`
- `ministries`: array de IDs dos ministérios que participam deste tipo de evento

---

## Ocorrências (Occurrences)

Uma ocorrência é a **realização de um schedule em uma data específica** (o culto do domingo dia 01/03/2026).

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/schedules/{id}/occurrences` | Listar ocorrências (inclui escalas) |
| POST | `/schedules/{id}/occurrences` | Criar ocorrência |
| GET | `/schedules/{id}/occurrences/{occId}` | Ver ocorrência com escalas |
| DELETE | `/schedules/{id}/occurrences/{occId}` | Remover ocorrência |

**Criar ocorrência — body:**
```json
{ "date": "2026-03-01" }
```

> Para schedules `recurring`, a data informada **deve bater com o `day_of_week`** do schedule (ex: um Culto Dominical só aceita datas de domingo). A API retorna 422 caso contrário.

**Resposta do GET show (com escalas):**
```json
{
  "success": true,
  "data": {
    "id": 5,
    "schedule_id": 1,
    "date": "2026-03-01",
    "schedule": { "name": "Culto Dominical", ... },
    "duties": [
      {
        "id": 1,
        "member_id": 10,
        "ministry_id": 2,
        "role": "Guitarrista",
        "member": { "id": 10, "name": "João" },
        "ministry": { "id": 2, "name": "Louvor" }
      }
    ]
  }
}
```

---

## Escalas (Duties)

Escalas vinculam um membro a uma ocorrência, dentro de um ministério.

| Método | Rota | Descrição |
|--------|------|-----------|
| POST | `/schedules/{id}/occurrences/{occId}/duties` | Escalar membro |
| DELETE | `/schedules/{id}/occurrences/{occId}/duties/{dutyId}` | Remover escala |

**Escalar membro — body:**
```json
{
  "member_id": 10,
  "ministry_id": 2,
  "role": "Guitarrista"
}
```

- `role` é opcional (ex: "Guitarra", "Vocal", "Câmera 1")
- `ministry_id` deve ser um dos ministérios definidos no schedule pai
- `member_id` deve pertencer ao ministério informado

**Validações retornam 422 com mensagem explicativa caso falhem.**

---

## Eleições

Sistema de votação para eleger membros da congregação.

| Método | Rota | Auth | Descrição |
|--------|------|------|-----------|
| GET | `/elections` | Sim | Listar eleições |
| POST | `/elections` | Sim | Criar eleição |
| GET | `/elections/{id}` | Sim | Ver eleição |
| PUT | `/elections/{id}` | Sim | Atualizar |
| DELETE | `/elections/{id}` | Sim | Remover |
| POST | `/elections/{id}/members` | Sim | Adicionar candidatos |
| PUT | `/elections/{id}/members/order` | Sim | Reordenar candidatos |
| DELETE | `/elections/{id}/members/{memberId}` | Sim | Remover candidato |
| GET | `/elections/active/public` | Não | Eleições ativas (público) |
| GET | `/elections/{id}/public` | Não | Ver eleição ativa (público) |
| GET | `/elections/{id}/statistics` | Sim | Resultado da votação |

**Status de eleição:** `draft` → `active` → `finished` / `cancelled`

---

## Votação (fluxo QR Code)

1. Admin gera tokens via `/token-groups/{id}/tokens`
2. Token é impresso em QR Code e entregue ao membro
3. Membro scanneia → frontend chama `POST /tokens/validate` com o token
4. Frontend exibe a eleição ativa e o membro vota via `POST /vote`

| Método | Rota | Auth | Descrição |
|--------|------|------|-----------|
| POST | `/tokens/validate` | Não | Validar token QR e retornar eleições pendentes |
| POST | `/vote` | Não | Registrar voto por token |
| POST | `/vote-by-cpf` | Não | Registrar voto por CPF |
| POST | `/members/validate-cpf` | Não | Verificar CPF e histórico de votos |

**Votar — body:**
```json
{
  "token": "abc123...",
  "election_id": 1,
  "voted_member_id": 5
}
```

---

## Grupos de Tokens

Agrupam tokens QR para facilitar a gestão (ex: um grupo por eleição/turma).

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/token-groups` | Listar grupos |
| POST | `/token-groups` | Criar grupo |
| GET | `/token-groups/{id}` | Ver grupo |
| PUT | `/token-groups/{id}` | Atualizar |
| DELETE | `/token-groups/{id}` | Remover |
| GET | `/token-groups/{id}/tokens` | Listar tokens do grupo |
| POST | `/token-groups/{id}/tokens` | Gerar tokens (`{ "quantity": 100 }`) |
| DELETE | `/token-groups/{id}/tokens/{tokenId}` | Remover token (somente se não usado) |
| POST | `/token-groups/{id}/attach-elections` | Vincular eleições |
| POST | `/token-groups/{id}/detach-elections` | Desvincular eleições |
| GET | `/token-groups/{id}/active-elections` | Eleições ativas do grupo |

---

## Usuários do Sistema

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/users` | Listar usuários |
| POST | `/users` | Criar usuário |
| GET | `/users/{id}` | Ver usuário |
| PUT | `/users/{id}` | Atualizar |
| DELETE | `/users/{id}` | Remover |

Permissões: `admin` (acesso total) ou `viewer` (somente leitura).
