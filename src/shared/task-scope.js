export const TASK_SCOPE_ALL = 'all';
export const TASK_SCOPE_AUTHORED = 'authored';
export const TASK_SCOPE_ASSIGNED = 'assigned';

/**
 * @param {Array<object>} tasks
 * @param {string}      scope
 * @param {number}      currentUserId
 * @return {Array<object>}
 */
export function filterTasksByScope( tasks, scope, currentUserId ) {
	return tasks.filter( ( task ) => {
		if ( scope === TASK_SCOPE_AUTHORED ) {
			return Number( task.author ) === Number( currentUserId );
		}

		if ( scope === TASK_SCOPE_ASSIGNED ) {
			return Number( task.assignee || 0 ) === Number( currentUserId );
		}

		return true;
	} );
}
