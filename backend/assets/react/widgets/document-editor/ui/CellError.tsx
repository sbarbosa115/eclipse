/** The message under a grid cell's control; the control points at it with aria-describedby. */
export function CellError({id, message}: {id: string; message?: string}) {
  if (!message) return null;
  return (
    <span id={id} className="field-error">
      {message}
    </span>
  );
}

/** The aria attributes of a control whose error may show under it. */
export function invalidProps(id: string, message?: string) {
  return message
    ? {'aria-invalid': true as const, 'aria-describedby': id}
    : {};
}
