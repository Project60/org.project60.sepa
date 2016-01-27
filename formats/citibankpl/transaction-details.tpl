{foreach from=$contributions item="contribution"}
0
{$contribution.contribution_id|mb_truncate:10:"":true:false}
1
{$contribution.iban|regex_replace:'/[A-Z][A-Z]/':""}
2
{$contribution.iban|regex_replace:'/[A-Z][A-Z][0-9][0-9]/':""|truncate:8:""}
3
{$creditor.iban|regex_replace:'/[A-Z][A-Z]/':""|truncate:10:""}
4
{$contribution.display_name|mb_truncate:35:"":true:false}
{$contribution.street_address|mb_truncate:35:"":true:false}
{$contribution.postal_code|cat:' '|cat:$contribution.city|mb_truncate:35:"":true:false}
6
{$contribution.total_amount}
7
{$contribution.receive_date|date_format:"%d/%m/%y"}
8
{$contribution.reference|mb_truncate:20:"":true:false}
{'ID'|cat:$contribution.contribution_id|mb_truncate:35:"":true:false}
-1
{/foreach}