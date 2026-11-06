import { normalizar, resolver } from '../public/assets/js/comandos-voz.mjs';
let input='';for await(const chunk of process.stdin) input+=chunk;
const catalogos=JSON.parse(input);let assertions=0;
const check=(v,m)=>{if(!v)throw new Error(m);assertions++;};
try {
    for(const [rol,catalogo] of Object.entries(catalogos)) {
        for(const c of catalogo) for(const frase of c.frases) {
            check(resolver(frase,catalogo)?.id===c.id,'Comando permitido '+rol);
            check(resolver('  '+frase.toUpperCase().replaceAll(' ','   ')+'  ',catalogo)?.id===c.id,'Mayúsculas y espacios '+rol);
        }
        for(const ataque of ['cancelar citas','reprogramar cita','confirmar cita','registrar asistencia','vincular cuenta','cerrar sesion','abrir alergias','ver resultados de laboratorio','abrir antecedentes','diagnosticar','ver citas del paciente 123','abrir mis citas de otra persona','consultar mi historial CI 123','/mis-citas/1','https://example.invalid','javascript:alert(1)','abrir agenda; cancelar citas','<img src=x onerror=alert(1)>',null,{},[],42,'x'.repeat(161)]) {
            check(resolver(ataque,catalogo)===null,'Texto ajeno al catálogo no se interpreta '+rol);
        }
    }
    check(resolver('¿VER MÍS PRÓXIMAS CÍTAS?',catalogos.paciente)?.id==='proximas','Acentos y puntuación');
    check(normalizar('  VER\tMIS\nPRÓXIMAS citas. ')==='ver mis proximas citas','Normalización Unicode y espacios');
    for(const rol of ['medico','sin_vinculo','administrador','recepcionista']) check(resolver('abrir mis citas',catalogos[rol])===null,'Sin suplantar un Paciente');
    for(const rol of ['paciente','sin_vinculo','medico']) check(resolver('abrir reportes',catalogos[rol])===null,'Reportes solo administrativos');
    for(const ruta of ['//example.invalid','https://example.invalid','/pacientes/1/alergias','/logout','/mis-citas/1/cancelar','/dashboard?transcripcion=privado','/agenda/../pacientes']) {
        check(resolver('ir al inicio',[{id:'inicio',frases:['ir al inicio'],ruta}])===null,'Destino manipulado bloqueado');
    }
    process.stdout.write(JSON.stringify({assertions}));
} catch(e) {process.stdout.write(JSON.stringify({error:e.message,assertions}));process.exitCode=1;}
