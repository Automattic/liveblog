import Link, { findLinkEntities } from './Link';
import Emoji, { findEmojiEntities } from './Emoji';

export default [
  {
    strategy: findLinkEntities,
    component: Link,
  },
  {
    strategy: findEmojiEntities,
    component: Emoji,
  },
];
