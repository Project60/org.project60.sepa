<body>
{literal}
<style>body {font-family: 'sans-serif'}
td {padding:1px 15px;}

</style>
{/literal}
<table>
<tr>
<td width="180">
<img src="http://upload.wikimedia.org/wikipedia/commons/thumb/7/75/Wikimedia_France_logo.svg/460px-Wikimedia_France_logo.svg.png"/ width=50 height=50>
</td><td>
<h2>Mandat de prélèvement SEPA</h2>
</td><td  width="300" align="right">
 {$sepa.reference}
</td></tr></table> 

<table>
<tr><td><i>Prénom</i></td><td>{$contact.first_name}</td></tr>
<tr><td><i>Nom</i></td><td>{$contact.last_name}</td></tr>
<tr><td><i>Adresse</i></td><td>{$contact.street_address}<br/>
{$contact.postal_code} {$contact.city}<br/>
</td></tr>
<tr><td><i>Email</i></td><td>{$contact.email}</td></tr>
</table> 
  
<h3>Coordonnées bancaire </h3>
<b>Merci de joindre obligatoirement un RIB avec ce Mandat de prélèvement</b> 

<table>
<tr>
<td width="44%">
 <i>Numéro d’identification international du compte bancaire<br/>IBAN (International Bank Account Number) </i>
</td>
<td>
{$sepa.iban}</td>
</tr><tr>
<td><i>
Code international d’identification de votre banque<br>BIC (Bank Identifier Code) 
</i></td><td>
{$sepa.bic}
</td></tr>
 </table>
<br> 
<table>
<tr>
<td>
Nom du créancier</td></td><td>WIKIMÉDIA FRANCE</td>
</tr><tr>
<td>I.C.S</td><td>{$creditor}</td>
</tr><tr>
<td> 
Adresse du créancier</td><td>27 avenue Ledru Rollin, 75012 Paris</td>
</tr>
 <tr>
<td>Type de don </td><td>Mensuel</td></tr>
</table>
<br> 
Je souhaite recevoir un reçu fiscal tous les ans pour l’ensemble de mes dons me permettant d’en déduire 66% du 
montant de mes impôts sur le revenu : <br> OUI   &#9744;       NON   &#9744; 
<br>
Fait à :  
 
<br>
<br>
Le ___ / ___ / _______ 
<br>
<br>
<br>
 
 
 
Signature : 
 <br><br>
Merci d’avance pour votre soutien 
 <br> 
 
<div style="font-size:9px">
<i>En signant ce formulaire, vous autorisez Wikimédia France à envoyer des instructions à votre banque pour débiter votre compte, et autorisez votre banque à débiter votre compte conformément aux instructions de Wikimédia France. Vous bénéficiez du droit d’être remboursé par votre banque selon les conditions décrites dans la convention que vous avez passée avec elle. Une demande de remboursement doit être présentée dans les 8 semaines suivant la date de débit de votre compte pour un prélèvement autorisé  ou  sans  tarder  et  au  plus  tard  dans  les  13  mois  en  cas  de  prélèvement  non  autorisé.  
<br>
Note :  Vos  droits  concernant  le  présent  mandat  sont  expliqués  dans  le  document que vous pouvez obtenir auprès de votre banque. 
Les informations contenues dans le présent mandat, qui doit être complété, sont destinées à n’être utilisées par le créancier que pour la gestion de sa relation avec son client ou donateur. Elles pourront donner lieu à l’exercice, par ce dernier, de ses droits d’oppositions, d’accès et de rectification tels que prévus aux articles 38 et suivants  de la loi n°78‐17 du 6 janvier 1978 relative à l’informatique, aux fichiers et aux libertés. 
Vous pouvez à tout moment modifier votre montant de {$recur.amount}€ montant, recevoir votre reçu fiscal ou interrompre votre prélèvement en nous contactant par courrier, email (dons@wikimedia.fr) ou par téléphone. 
<br>
Pour toute question concernant vos dons, n’hésitez pas à nous contacter au 09 80 93 07 54.
</div>
</body>
