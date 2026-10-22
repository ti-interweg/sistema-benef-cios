<?php
/**
 * Definição do questionário de Anamnese (fixo).
 * Usado pelo formulário público (anamnese.php) e pela devolutiva (resultados).
 * Tipos: 'secao' (título de seção), 'radio' (1 opção), 'checkbox' (várias), 'texto' (aberta).
 * 'q' é o texto da pergunta (também usado como chave da resposta).
 */
$ANAMNESE_PERGUNTAS = [
  ['tipo'=>'secao','titulo'=>'Sono'],
  ['tipo'=>'radio','obr'=>true,'q'=>'Quantas horas, em média, você dorme por noite?','op'=>['Menos de 5h','5- 6h','6- 7h','7- 8h','Mais que 8h']],
  ['tipo'=>'radio','obr'=>true,'q'=>'Você sente que essa quantidade de sono é suficiente para sua recuperação?','op'=>['Sim','Parcialmente','Não']],
  ['tipo'=>'texto','q'=>'Se necessário, fale mais sobre','grupo'=>'sono1'],
  ['tipo'=>'radio','obr'=>true,'q'=>'Com que frequência você tem dificuldade para dormir (insônia)?','op'=>['Nunca','Raramente (1x/ mês)','Às vezes (1x / semana)','Frequentemente (2-3x / semana)','Quase sempre (4x ou mais / semana)']],
  ['tipo'=>'texto','q'=>'Porque?','grupo'=>'insonia'],
  ['tipo'=>'checkbox','obr'=>true,'q'=>'O que costuma impactar negativamente seu sono?','op'=>['Estresse/ansiedade','Uso de celular antes de dormir','Trabalho até tarde','Alimentação noturna pesada','Consumo de cafeína/estimulantes','Ambiente (luz, barulho, temperatura)','Pensamentos acelerados']],
  ['tipo'=>'texto','q'=>'Se necessário, fale mais sobre','grupo'=>'sono2'],

  ['tipo'=>'secao','titulo'=>'Saúde e histórico'],
  ['tipo'=>'radio','obr'=>true,'q'=>'Você possui alguma condição de saúde diagnosticada?','op'=>['Não','Sim']],
  ['tipo'=>'checkbox','q'=>'Se sim, qual?','grupo'=>'cond','op'=>['Hipertensão','Diabetes','Colesterol','Problemas vasculares','Doenças autoimunes','Lipedema','Alterações gastrointestinais','Ansiedade ou Depressão','Alterações hormonais','Outro']],
  ['tipo'=>'radio','obr'=>true,'q'=>'Existe histórico familiar de doenças relevantes?','op'=>['Não','Sim']],
  ['tipo'=>'checkbox','q'=>'Se sim, qual?','grupo'=>'histdoenca','op'=>['Hipertensão','Diabetes','Doenças cardiovasculares','Doenças autoimunes','Alterações neurológicas','Outro']],
  ['tipo'=>'checkbox','q'=>'Histórico familiar de câncer','op'=>['Mama','Próstata','Intestino','Pele','Pulmão','Outro']],

  ['tipo'=>'secao','titulo'=>'Rotina e hábitos'],
  ['tipo'=>'radio','obr'=>true,'q'=>'Quanto tempo do seu dia você passa sentado(a)?','op'=>['Menos de 4h','4–6h','6–8h','Mais de 8h']],
  ['tipo'=>'radio','obr'=>true,'q'=>'Você costuma fazer pausas ao longo do dia?','op'=>['Sim, frequentemente','Às vezes','Raramente','Nunca']],
  ['tipo'=>'radio','obr'=>true,'q'=>'Em média, quanto tempo você passa no celular/redes sociais por dia?','op'=>['Menos de 2h','2–4h','4–6h','Mais de 6h']],

  ['tipo'=>'secao','titulo'=>'Dores e sintomas'],
  ['tipo'=>'radio','obr'=>true,'q'=>'Com que frequência você sente dores nas pernas?','op'=>['Nunca','Raramente (1x/mês)','Às vezes (1x/semana)','Frequentemente (2–3x/semana)','Quase sempre (4x ou mais/semana)']],
  ['tipo'=>'texto','q'=>'Caso sinta, consegue identificar o motivo para as dores?','grupo'=>'dorpernas'],
  ['tipo'=>'radio','obr'=>true,'q'=>'Com que frequência você sente dores de cabeça?','op'=>['Nunca','Raramente (1x/mês)','Às vezes (1x/semana)','Frequentemente (2–3x/semana)','Quase sempre (4x ou mais/semana)']],
  ['tipo'=>'texto','q'=>'Caso sinta, consegue identificar o motivo para as dores?','grupo'=>'dorcabeca'],
  ['tipo'=>'checkbox','obr'=>true,'q'=>'Você apresenta algum desses sintomas com frequência? Se sim, qual?','op'=>['Cansaço excessivo','Falta de energia','Tontura','Diarreia','Dor de estômago','Ansiedade','Não','Outro']],
  ['tipo'=>'texto','q'=>'Caso sinta, consegue identificar o motivo?','grupo'=>'sintomas'],
  ['tipo'=>'radio','obr'=>true,'q'=>'Você sente dores no corpo atualmente? Se sim, onde?','op'=>['Não','Sim']],
  ['tipo'=>'checkbox','q'=>'Se sim, onde?','grupo'=>'dorcorpo','op'=>['Pescoço','Ombros','Coluna','Pernas','Outro']],
  ['tipo'=>'radio','q'=>'Intensidade da dor','grupo'=>'dorcorpo','op'=>['Leve','Moderada','Intensa']],
  ['tipo'=>'radio','q'=>'Frequência da dor','grupo'=>'dorcorpo','op'=>['Raramente (1x/mês)','Às vezes (1x/semana)','Frequentemente (2–3x/semana)','Quase sempre (4x+/semana)']],
  ['tipo'=>'radio','q'=>'Você buscou ajuda para essa dor?','grupo'=>'dorcorpo','op'=>['Não','Sim, médico','Sim, fisioterapeuta','Sim, outro profissional']],
  ['tipo'=>'radio','q'=>'Você faz uso de medicação para essa dor?','grupo'=>'dorcorpo','op'=>['Não','Sim, eventualemente','Sim, com frequência']],
  ['tipo'=>'texto','q'=>'Você identifica o motivo para essa dor?','grupo'=>'dorcorpo'],

  ['tipo'=>'secao','titulo'=>'Alimentação e hidratação'],
  ['tipo'=>'radio','obr'=>true,'q'=>'Quantos litros de água você bebe por dia?','op'=>['Menos de 1','2–3','+3','Não bebe']],
  ['tipo'=>'radio','obr'=>true,'q'=>'Você costuma pular refeições?','op'=>['Não','Sim']],
  ['tipo'=>'checkbox','q'=>'Se sim, qual?','grupo'=>'pular','op'=>['Café da manhã','Almoço','Jantar']],
  ['tipo'=>'texto','q'=>'Porque?','grupo'=>'pular'],
  ['tipo'=>'radio','obr'=>true,'q'=>'Você identifica episódios de fome emocional?','op'=>['Nunca','Raramente','Às vezes','Frequentemente']],
  ['tipo'=>'texto','q'=>'Consegue identificar os motivos desses episódios?','grupo'=>'fome'],

  ['tipo'=>'secao','titulo'=>'Atividade física'],
  ['tipo'=>'radio','obr'=>true,'q'=>'Você pratica atividade física atualmente?','op'=>['Não','Sim, até 2x/semana','Sim, 3x ou mais']],
  ['tipo'=>'texto','q'=>'Se sim: que tipo de atividade você pratica?','grupo'=>'af'],
  ['tipo'=>'radio','q'=>'Se não: pretende iniciar?','grupo'=>'af','op'=>['Sim','Não']],
  ['tipo'=>'texto','q'=>'O que mais te impede hoje?','grupo'=>'af'],
  ['tipo'=>'radio','obr'=>true,'q'=>'Você conhece nossos benefícios e ações relacionados à atividade física (Totalpass / Wellhub / Desafio Fitness / Desafio de Passos)?','op'=>['Não','Parte deles','Sim, todos eles']],

  ['tipo'=>'secao','titulo'=>'Cuidado e prevenção'],
  ['tipo'=>'radio','obr'=>true,'q'=>'Com que frequência você realiza exames médicos preventivos?','op'=>['Nunca','A cada 2 anos ou mais','1x por ano','Mais de 1x por ano']],
  ['tipo'=>'radio','obr'=>true,'q'=>'Quantos medicamentos foram prescritos para você nos últimos 6 meses?','op'=>['Nenhum','1-2','3-4','5 ou mais']],
  ['tipo'=>'texto','q'=>'Se sim: Quais e porque?','grupo'=>'medic'],
  ['tipo'=>'radio','obr'=>true,'q'=>'Como você avalia seu nível de estresse?','op'=>['Baixo','Moderado','Alto']],
  ['tipo'=>'texto','q'=>'Você consegue identificar motivos para o estresse?','grupo'=>'estresse'],

  ['tipo'=>'secao','titulo'=>'Uso de pronto-socorro'],
  ['tipo'=>'radio','obr'=>true,'q'=>'Com que frequência você procura pronto-socorro ou atendimento de urgência?','op'=>['Nunca','Raramente (1x ao ano)','Às vezes (2–3x ao ano)','Frequentemente (4x ou mais ao ano)']],
  ['tipo'=>'radio','obr'=>true,'q'=>'Em quais situações você costuma procurar o pronto-socorro?','op'=>['Quando a dor ou sintoma já está intenso','Quando percebo os primeiros sinais','Como primeira opção, mesmo sem gravidade','Quando os sintomas persistem','Outro']],
  ['tipo'=>'radio','obr'=>true,'q'=>'Antes de ir ao pronto-socorro, você costuma:','op'=>['Procurar um médico previamente (consulta agendada)','Tentar resolver com medicação por conta própria','Esperar melhorar sozinho(a)','Tenta uma consulta online','Outro']],

  ['tipo'=>'secao','titulo'=>'Percepção geral'],
  ['tipo'=>'radio','obr'=>true,'q'=>'De modo geral, como você avaliaria sua saúde hoje?','op'=>['Muito ruim','Ruim','Regular','Boa','Muito boa']],
  ['tipo'=>'texto','obr'=>true,'q'=>'Qual aspecto da sua saúde você mais gostaria de melhorar?'],
];
