/**
 * @deprecated Use navigation.ts instead. This file is kept for backward compatibility.
 * All navigation config has been consolidated into navigation.ts
 */
export {
  navigationGroups,
  getAllNavItems,
  findNavItemByHref,
  findGroupByHref,
  iconMap,
  getIconName,
  getIconComponent,
} from './navigation.ts'

// Legacy: flat array for backward compatibility
import { getAllNavItems } from './navigation.ts'

/**
 * @deprecated Use getAllNavItems() from navigation.ts instead
 */
export const navigationConfig = getAllNavItems()
