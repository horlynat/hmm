// Remplace le paquet `server-only` sous Vitest : sa version « client » lève
// volontairement une erreur à l'import (garde-fou de bundling Next.js), ce
// qui empêcherait de tester unitairement les modules serveur. Aucun effet
// sur le build Next, qui résout le vrai paquet.
export {};
