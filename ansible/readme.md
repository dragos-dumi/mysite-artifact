Make sure python 2.7 is installed on target servers
use: apt install -y python-minimal

Ansible quick commands
```
ansible-galaxy install -r requirements.yml

ansible-playbook --vault-password-file vault.password -u root -i mysite/hosts mysite.yml 

ansible-playbook --vault-password-file vault.password -u root -i mysite/hosts mysite.yml --tags security

ansible-vault create mysite-vault.yml

```