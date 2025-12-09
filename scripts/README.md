# Scripts de Manutenção

## fix-permissions.sh

Script para corrigir permissões após fazer `git pull` ou deploy.

### Uso:

```bash
# No servidor, após fazer pull:
cd /home/mcaquabeat.hotlead.es/public_html/ranking/scripts
sudo ./fix-permissions.sh
```

### O que faz:

- Ajusta dono de todos os arquivos em `data/` para `mcaqu3731`
- Define permissão 664 para arquivos JSON (leitura/escrita)
- Define permissão 755 para pastas (navegável)
- Ajusta uploads de CSV

### Hook Automático (Opcional):

Para executar automaticamente após cada pull, adicione ao `.git/hooks/post-merge`:

```bash
#!/bin/bash
cd "$(git rev-parse --show-toplevel)"
./scripts/fix-permissions.sh
```
