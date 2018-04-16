import Link, { findLinkEntities } from './Link';
import Emoji, { findEmojiEntities } from './Emoji';
import Text, { findTextEntities } from './Text';

export default [
  {
    strategy: findLinkEntities,
    component: Link,
  },
  {
    strategy: findEmojiEntities,
    component: Emoji,
  },
  {
    strategy: findTextEntities,
    component: Text,
  },
];
