# Logo

Lockups `ATELIER \ DIAGRAM`, derives du logo Atelier.

| Fichier | Boite | Contenu |
| --- | --- | --- |
| `lockup-h*.svg` | 688 x 180 | monogramme, wordmark, tagline, horizontal |
| `lockup-v*.svg` | 600 x 600 | monogramme au-dessus, wordmark, tagline |
| `wordmark-h*.svg` | 492 x 96 | wordmark seul |
| `mark*.svg` | 414 x 423 | monogramme seul |

Le suffixe `-dark` porte un fond `#08090f`, `-light` un fond `#fdfaf3`. Sans suffixe, le
fond est transparent et le texte est calibre pour rester lisible sur clair comme sur sombre.

## Usage

Dans un README, servir la paire dark/light au theme du lecteur:

```html
<picture>
  <source media="(prefers-color-scheme: dark)" srcset="assets/logo/lockup-h-dark.svg">
  <img src="assets/logo/lockup-h-light.svg" alt="Atelier Diagram" width="344">
</picture>
```

Sur un fond dont vous ne maitrisez pas la couleur, utiliser la variante transparente
`lockup-h.svg`.

## Contraintes

- Ne pas recolorer le monogramme ni le gradient (`#00b0fc` vers `#5e4da1`).
- Ne pas separer le wordmark de sa barre oblique.
- Ne pas ajouter d'ombre portee.
- Le texte utilise `system-ui`: le rendu suit la machine du lecteur. Ne pas substituer
  une webfont, ne pas convertir en `<image>`.
