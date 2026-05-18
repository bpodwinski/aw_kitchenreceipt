# Kitchen Receipt Auto Print

Module PrestaShop qui génère automatiquement un PDF et imprime les tickets de cuisine pour les restaurants et cafés à la validation d'une commande.

- **Nom technique** : `aw_kitchenreceipt`
- **Version** : 1.1.0
- **Auteur** : Artisan Webmaster
- **Compatibilité PrestaShop** : 1.7+
- **Licence** : Academic Free License (AFL 3.0)

## Fonctionnalités

- Génération automatique d'un PDF à chaque nouvelle commande via le hook `actionValidateOrder`.
- Génération manuelle d'un PDF à partir d'un identifiant de commande depuis la configuration du module.
- Format de ticket adapté aux imprimantes thermiques (largeur configurable, par défaut 80 mm).
- Police personnalisée `Ticketing` embarquée pour un rendu type ticket de caisse.
- Affichage de la référence de commande, de la date, du client et du détail des produits (quantité, nom, attributs).
- Intégration optionnelle avec le module `prestatilldrive` pour afficher le créneau de retrait dans l'entête du ticket.
- Sauvegarde des PDF générés dans le dossier `pdf/` du module.

## Structure du projet

```
aw_kitchenreceipt/
├── aw_kitchenreceipt.php   Module principal (installation, hooks, configuration, génération PDF)
├── aw_pdf.php              Classe AWPDF étendant TCPDF (entête, pied de page, intégration créneau Drive)
├── config.xml              Métadonnées du module PrestaShop
├── index.php               Protection d'accès direct
├── logo.png                Logo du module
├── translations/fr-FR/     Traductions françaises (format XLIFF)
└── vendor/fonts/           Police Ticketing.ttf utilisée par TCPDF
```

## Installation

1. Copier le dossier `aw_kitchenreceipt` dans le répertoire `modules/` de votre installation PrestaShop.
2. Dans le back-office, aller dans **Modules > Gestionnaire de modules**.
3. Rechercher *Kitchen Receipt Auto Print* puis cliquer sur **Installer**.

À l'installation, le module :

- Enregistre la police `Ticketing.ttf` auprès de TCPDF.
- Initialise les valeurs de configuration par défaut.
- S'abonne au hook `actionValidateOrder`.

## Configuration

Depuis l'écran de configuration du module, vous pouvez :

| Option | Description | Valeur par défaut |
|---|---|---|
| `PDF_GENERATION` | Active la génération automatique d'un PDF à la validation d'une commande | `true` |
| `PDF_ORIENTATION` | Orientation du PDF (`P` portrait, `L` paysage) | `P` |
| `PDF_UNIT_MEASURE` | Unité de mesure (`pt`, `mm`, `cm`, `in`) | `mm` |
| `PDF_WIDTH` | Largeur du ticket | `80` |
| `PDF_FOR_PRINTING` | Génère aussi le PDF côté serveur pour l'impression | `false` |

Un second formulaire permet de **générer manuellement** un PDF à partir d'un `Order ID`.

## Fonctionnement

1. À la validation d'une commande, le hook `hookactionValidateOrder` est déclenché.
2. Si la génération PDF est activée, la méthode `generatePdf($orderId, 'F', $filePath)` est appelée.
3. Le PDF est enregistré dans `modules/aw_kitchenreceipt/pdf/` sous la forme `order_YYYYMMDDHHMMSS.pdf`.
4. Le contenu inclut la référence, la date, le client et la liste des produits avec leurs attributs.

Modes de sortie supportés par `generatePdf` :

- `I` : affichage dans le navigateur
- `D` : téléchargement
- `F` : sauvegarde sur le serveur
- `FI` / `FD` : sauvegarde + affichage ou téléchargement

## Désinstallation

La désinstallation supprime toutes les clés de configuration (`AW_KR_*`) et désenregistre le hook `actionValidateOrder`.

## Licence

Distribué sous Academic Free License (AFL 3.0).
