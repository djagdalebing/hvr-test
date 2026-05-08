import {Pipe, PipeTransform} from '@angular/core';
@Pipe({name:'snake-case.pipeStub'})
export class SnakeCasePipe implements PipeTransform { transform(v: any) { return v; } }
